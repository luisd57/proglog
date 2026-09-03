// Drives post-merge-nudge.mjs the way Claude Code does: JSON on stdin, read the
// verdict. A stub gh on PATH keeps every case offline and deterministic.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync, chmodSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, delimiter } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'post-merge-nudge.mjs');
const WIN = process.platform === 'win32';

// A gh that answers from an env var instead of the network.
function stubGh() {
  const dir = mkdtempSync(join(tmpdir(), 'stub-gh-'));
  const js = join(dir, 'gh-impl.mjs');
  writeFileSync(js, `
const mode = process.env.STUB_GH_MODE ?? 'merged';
if (mode === 'missing') process.exit(127);
if (mode === 'error') { process.stderr.write('no pull requests found'); process.exit(1); }
if (mode === 'slow') { const until = Date.now() + 30000; while (Date.now() < until); }
process.stdout.write(JSON.stringify({ state: mode === 'open' ? 'OPEN' : 'MERGED' }));
`, 'utf8');

  if (WIN) {
    writeFileSync(join(dir, 'gh.cmd'), `@echo off\r\nnode "${js}" %*\r\n`, 'utf8');
  } else {
    const sh = join(dir, 'gh');
    writeFileSync(sh, `#!/bin/sh\nexec node "${js}" "$@"\n`, 'utf8');
    chmodSync(sh, 0o755);
  }
  return dir;
}

const GH_DIR = stubGh();

function run(payload, mode = 'merged') {
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [HOOK], {
      stdio: ['pipe', 'pipe', 'pipe'],
      env: { ...process.env, PATH: `${GH_DIR}${delimiter}${process.env.PATH}`, STUB_GH_MODE: mode },
    });
    let out = '';
    child.stdout.on('data', (c) => { out += c; });
    child.on('close', () => {
      if (!out.trim()) return resolve('QUIET');
      try {
        resolve(JSON.parse(out).hookSpecificOutput.additionalContext ? 'NUDGE' : 'QUIET');
      } catch { resolve('QUIET'); }
    });
    child.stdin.end(JSON.stringify(payload));
  });
}

const bash = (command) => ({
  tool_name: 'Bash',
  cwd: process.cwd(),
  tool_input: { command },
  // What a real merge actually captures: nothing. This is the case the old
  // text-matching version could never fire on.
  tool_response: { stdout: '', stderr: '', interrupted: false },
});

const cases = [
  ['plain merge, PR is MERGED', bash('gh pr merge 76 --squash'), 'merged', 'NUDGE'],
  ['the exact command that shipped PR #76',
    bash('git checkout main 2>&1 | tail -1 && gh pr merge 76 --squash --delete-branch 2>&1 | tail -4'), 'merged', 'NUDGE'],
  ['merge with a PR url', bash('gh pr merge https://github.com/o/r/pull/76 --squash'), 'merged', 'NUDGE'],

  ['PR still OPEN, so the merge did not land', bash('gh pr merge 76 --squash'), 'open', 'QUIET'],
  ['--auto queues behind checks, nothing merged yet', bash('gh pr merge 76 --auto --squash'), 'merged', 'QUIET'],
  ['gh missing from PATH', bash('gh pr merge 76 --squash'), 'missing', 'QUIET'],
  ['gh errors out', bash('gh pr merge 76 --squash'), 'error', 'QUIET'],
  ['gh hangs, hook must not', bash('gh pr merge 76 --squash'), 'slow', 'QUIET'],

  ['no selector, current branch is gone', bash('gh pr merge --squash'), 'merged', 'QUIET'],
  ['digits inside --body are not a PR number', bash('gh pr merge --squash --body "Fixes 76"'), 'merged', 'QUIET'],

  ['not a merge at all', bash('gh pr view 76'), 'merged', 'QUIET'],
  ['merge named inside a commit message', bash('git commit -m "explain gh pr merge 76"'), 'merged', 'QUIET'],
  ['merge named in an echo', bash('echo "run gh pr merge 76 next"'), 'merged', 'QUIET'],

  // The live false positive: separators inside the quoted string used to end the
  // segment early, handing "gh pr merge 76" a clean head.
  ['merge inside an echoed json payload',
    bash(`echo '{"command":"git checkout main && gh pr merge 76 --squash"}' | node hook.mjs`), 'merged', 'QUIET'],
  ['merge inside a commit message that chains',
    bash('git commit -m "ran a && gh pr merge 76" && git push'), 'merged', 'QUIET'],
  ['not a Bash tool call', { tool_name: 'Edit', tool_input: { file_path: 'x.md' } }, 'merged', 'QUIET'],
];

let bad = 0;
const started = Date.now();
for (const [name, payload, mode, want] of cases) {
  const got = await run(payload, mode);
  const ok = got === want;
  if (!ok) bad++;
  console.log(`${ok ? 'ok  ' : 'FAIL'}  ${want.padEnd(5)} ${name}${ok ? '' : `  -> got ${got}`}`);
}
console.log(`\n${bad === 0 ? 'all pass' : `${bad} FAILED`}  (${Date.now() - started}ms)`);
process.exit(bad === 0 ? 0 : 1);
