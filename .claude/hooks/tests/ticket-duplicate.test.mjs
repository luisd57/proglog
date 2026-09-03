// Drives ticket-duplicate.mjs the way Claude Code does: JSON on stdin, read the
// verdict. A throwaway .scratch tree keeps this independent of the real tracker.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'ticket-duplicate.mjs');

const ROOT = mkdtempSync(join(tmpdir(), 'dupe-'));
const ISSUES = join(ROOT, '.scratch', 'demo', 'issues');
mkdirSync(ISSUES, { recursive: true });

const body = (title, status) => `# ${title}\n\n**Status:** ${status}\n\nBody.\n`;
function existing(name, title, status) {
  writeFileSync(join(ISSUES, name), body(title, status), 'utf8');
}

existing('16-locator.md', '16 - Dashboard e2e flakes on an ambiguous "Invite Patient" locator', 'ready-for-agent');
existing('13-clock.md', '13 - Make the injected clock deterministic', 'ready-for-agent');
existing('02-shipped.md', '02 - Pin the fixture Instants the console tests build on', 'resolved');

const NEW = join(ISSUES, '19-new.md').replace(/\\/g, '/');

function run(filePath, content) {
  return new Promise((resolve) => {
    const child = spawn(process.execPath, [HOOK], { stdio: ['pipe', 'pipe', 'pipe'] });
    let out = '';
    child.stdout.on('data', (c) => { out += c; });
    child.on('close', () => {
      if (!out.trim()) return resolve('ALLOW');
      try { resolve(JSON.parse(out).hookSpecificOutput.permissionDecision.toUpperCase()); }
      catch { resolve('ALLOW'); }
    });
    child.stdin.end(JSON.stringify({
      tool_name: 'Write', cwd: ROOT,
      tool_input: { file_path: filePath, content },
    }));
  });
}

const cases = [
  ['restates an open ticket', NEW,
    body('19 - Dashboard e2e is flaky on the ambiguous Invite Patient locator', 'ready-for-agent'), 'DENY'],
  ['same subject, different word order', NEW,
    body('19 - The Invite Patient locator is ambiguous and the dashboard flakes', 'ready-for-agent'), 'DENY'],

  ['a genuinely new subject', NEW,
    body('19 - Adopt PHP static analysis', 'ready-for-agent'), 'ALLOW'],
  ['ticket 18 as actually filed, against the real neighbours', NEW,
    body('18 - Pin the fixture Instants the repository and console tests build on', 'ready-for-agent'), 'ALLOW'],
  ['two shared words is under the floor', NEW,
    body('19 - The dashboard locator', 'ready-for-agent'), 'ALLOW'],

  ['a resolved ticket does not block a new one', NEW,
    body('19 - Pin the fixture Instants the console tests build on', 'ready-for-agent'), 'ALLOW'],

  ['the waiver clears it and records why', NEW,
    `# 19 - Dashboard e2e flakes on the ambiguous Invite Patient locator\n\n` +
    `**Status:** ready-for-agent\n\n**Not a duplicate of:** 16, which is the button. This is the menu item.\n`,
    'ALLOW'],
  ['an empty waiver does not count', NEW,
    `# 19 - Dashboard e2e flakes on the ambiguous Invite Patient locator\n\n` +
    `**Status:** ready-for-agent\n\n**Not a duplicate of:**\n`,
    'DENY'],

  ['citing another ticket in the body is not a copy of it', NEW,
    `# 19 - Adopt PHP static analysis\n\n**Status:** ready-for-agent\n\n` +
    `Related: the dashboard e2e flakes on an ambiguous Invite Patient locator.\n`,
    'ALLOW'],

  ['a file outside the tracker is not gated', join(ROOT, 'notes.md'),
    body('19 - Dashboard e2e flakes on the ambiguous Invite Patient locator', 'ready-for-agent'), 'ALLOW'],
  ['a ticket with no Status line is not scored', NEW,
    '# 19 - Dashboard e2e flakes on the ambiguous Invite Patient locator\n\nNo status.\n', 'ALLOW'],
];

let bad = 0;
for (const [name, filePath, content, want] of cases) {
  const got = await run(filePath, content);
  const ok = got === want;
  if (!ok) bad++;
  console.log(`${ok ? 'ok  ' : 'FAIL'}  ${want.padEnd(5)} ${name}${ok ? '' : `  -> got ${got}`}`);
}

// Editing an existing ticket must never be gated.
const overwrite = await run(join(ISSUES, '16-locator.md'),
  body('16 - Dashboard e2e flakes on an ambiguous "Invite Patient" locator', 'resolved'));
const ok = overwrite === 'ALLOW';
if (!ok) bad++;
console.log(`${ok ? 'ok  ' : 'FAIL'}  ALLOW editing an existing ticket is not gated`);

console.log(bad === 0 ? '\nall pass' : `\n${bad} FAILED`);
process.exit(bad === 0 ? 0 : 1);
