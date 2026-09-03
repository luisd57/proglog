// Drives tracker-match.mjs the way Claude Code does. Fixtures are a throwaway
// .scratch tree, so the tests do not drift when the real tracker changes.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'tracker-match.mjs');

const ROOT = mkdtempSync(join(tmpdir(), 'tracker-'));
const ISSUES = join(ROOT, '.scratch', 'demo', 'issues');
mkdirSync(ISSUES, { recursive: true });

function ticket(name, title, status) {
  writeFileSync(join(ISSUES, name), `# ${title}\n\n**Status:** ${status}\n\nBody.\n`, 'utf8');
}

ticket('16-locator.md', '16 - Dashboard e2e flakes on an ambiguous "Invite Patient" locator', 'ready-for-agent');
ticket('09-password.md', '09 - Exercise every password rule in the dashboard e2e', 'ready-for-agent');
ticket('01-shipped.md', '01 - Dashboard flakes on an ambiguous Invite Patient locator', 'resolved');

const LOCATOR_FAILURE = `
  1) [chromium] - e2e/invite-patient.spec.ts:24:5 - shows the dialog
  Error: strict mode violation: getByRole('button', { name: 'Invite Patient' }) resolved to 2 elements
  dashboard e2e suite failed with 1 failure
`;

const CHECKS_FAILING = [
  'e2e\tfail\t3m02s\thttps://github.com/o/r/actions/runs/1/job/2\t',
  'test\tpass\t2m11s\thttps://github.com/o/r/actions/runs/1/job/3\t',
].join('\n');

function run(command, stdout) {
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [HOOK], { stdio: ['pipe', 'pipe', 'pipe'] });
    let out = '';
    child.stdout.on('data', (c) => { out += c; });
    child.on('close', () => {
      if (!out.trim()) return resolve({ verdict: 'QUIET', reason: '' });
      try {
        const parsed = JSON.parse(out);
        resolve({ verdict: parsed.decision === 'block' ? 'MATCH' : 'QUIET', reason: parsed.reason ?? '' });
      } catch { resolve({ verdict: 'QUIET', reason: '' }); }
    });
    child.stdin.end(JSON.stringify({
      tool_name: 'Bash', cwd: ROOT,
      tool_input: { command },
      tool_response: { stdout, stderr: '' },
    }));
  });
}

const cases = [
  ['run view, failure matches an open ticket', 'gh run view 1 --log-failed', LOCATOR_FAILURE, 'MATCH'],
  ['run watch counts too', 'gh run watch 1', LOCATOR_FAILURE, 'MATCH'],
  ['a local test run is not a CI read', 'vendor/bin/phpunit', LOCATOR_FAILURE, 'QUIET'],
  ['unrelated failure output', 'gh run view 1 --log-failed', 'Segmentation fault in the migration runner', 'QUIET'],
  ['empty output', 'gh run view 1 --log-failed', '', 'QUIET'],

  // The quoting bug, shared with the other two hooks.
  ['CI read named inside an echoed payload that chains',
    `echo '{"c":"x && gh run view 1 --log-failed"}' | node h.mjs`, LOCATOR_FAILURE, 'QUIET'],
  ['CI read named in a commit message that chains',
    'git commit -m "after gh run view 1" && git push', LOCATOR_FAILURE, 'QUIET'],

  // gh pr checks is not watched: its output is a job name, a status, a duration
  // and a URL, which cannot reach the overlap floor. Quiet on a matching failure
  // too, so this pins the command being out of scope rather than just scoring low.
  ['gh pr checks is not a watched command', 'gh pr checks 76', CHECKS_FAILING, 'QUIET'],
  ['gh pr checks stays quiet even on output that would match', 'gh pr checks 76', LOCATOR_FAILURE, 'QUIET'],
];

let bad = 0;
for (const [name, command, stdout, want] of cases) {
  const { verdict } = await run(command, stdout);
  const ok = verdict === want;
  if (!ok) bad++;
  console.log(`${ok ? 'ok  ' : 'FAIL'}  ${want.padEnd(5)} ${name}${ok ? '' : `  -> got ${verdict}`}`);
}

// A resolved ticket must never be offered, even when it scores well.
const { reason } = await run('gh run view 1 --log-failed', LOCATOR_FAILURE);
const leaked = reason.includes('01-shipped');
if (leaked) bad++;
console.log(`${leaked ? 'FAIL' : 'ok  '}  QUIET a resolved ticket is not offered`);

console.log(bad === 0 ? '\nall pass' : `\n${bad} FAILED`);
process.exit(bad === 0 ? 0 : 1);
