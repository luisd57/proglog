// Drives skill-gate.mjs the way Claude Code does: JSON on stdin, read the verdict.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const HOOK = join(import.meta.dirname, '..', 'skill-gate.mjs');

// Stand-in repo root. These paths are never read from disk - the hook only matches them
// against SOURCE and TEST_FILE - but they must keep using directories SOURCE_DIRS covers.
const REPO = '/w/proj';

const dir = mkdtempSync(join(tmpdir(), 'gate-'));

function fixture(name, lines) {
  const path = join(dir, name);
  writeFileSync(path, lines.join('\n'), 'utf8');
  return path;
}

// The user typed the slash command. This is what the transcript actually holds.
const typed = fixture('typed.jsonl', [
  JSON.stringify({ type: 'user', message: { content: '<command-name>/mattpocock-skills:to-tickets</command-name>' } }),
]);

// Claude tried to call it and was refused. The refusal still carries the input shape.
const refused = fixture('refused.jsonl', [
  JSON.stringify({ message: { content: [{ name: 'Skill', input: { skill: 'mattpocock-skills:to-tickets' } }] } }),
  JSON.stringify({ message: { content: [{ type: 'tool_result', content: 'Skill mattpocock-skills:to-tickets cannot be used with Skill tool due to disable-model-invocation.' }] } }),
]);

// Nothing at all.
const empty = fixture('empty.jsonl', [JSON.stringify({ type: 'user', message: { content: 'hello' } })]);

// The hook's own deny text, which names the skill with a leading slash.
const denyEcho = fixture('deny-echo.jsonl', [
  JSON.stringify({ message: { content: 'New ticket files go through /mattpocock-skills:to-tickets or /mattpocock-skills:wayfinder, and neither has run.' } }),
]);

// Transcript-line builders, in the shapes Claude Code actually writes.
const said = (text) => JSON.stringify({ message: { content: text } });
const ranSkill = (skill) => JSON.stringify({ message: { content: [{ name: 'Skill', input: { skill } }] } });
const ranBash = (command) => JSON.stringify({ message: { content: [{ name: 'Bash', input: { command } }] } });
const PR = 'gh pr create --fill';

// PreToolUse fires with the call already appended, so every Bash fixture ends with
// the call under judgement. Verified against a live transcript: a marker unique to
// a running command is already in the file while that command runs.

// One review, one PR, then a second PR with no review in between.
const twoPrs = fixture('two-prs.jsonl', [
  ranSkill('mattpocock-skills:code-review'),
  ranBash(PR),
  said('more work happened here'),
  ranBash(PR),
]);

// Same, but a fresh review before the second PR.
const twoPrsReviewed = fixture('two-prs-reviewed.jsonl', [
  ranSkill('mattpocock-skills:code-review'),
  ranBash(PR),
  said('more work happened here'),
  ranSkill('mattpocock-skills:code-review'),
  ranBash(PR),
]);

// The gate denied, Claude reviewed, then retried the identical command.
const deniedThenReviewed = fixture('denied-then-reviewed.jsonl', [
  ranBash(PR),
  said('deny: code-review has not run'),
  ranSkill('mattpocock-skills:code-review'),
  ranBash(PR),
]);

// "gh pr create" as prose and inside a commit message, never as a command.
const prosePr = fixture('prose-pr.jsonl', [
  ranSkill('mattpocock-skills:code-review'),
  said('the hook gates gh pr create on the review skill'),
  ranBash('git commit -m "document gh pr create"'),
]);

// tdd ran, a PR shipped, now a new src edit with no fresh tdd.
const tddBeforePr = fixture('tdd-before-pr.jsonl', [
  ranSkill('mattpocock-skills:tdd'),
  ranBash(PR),
]);

const tddAfterPr = fixture('tdd-after-pr.jsonl', [
  ranSkill('mattpocock-skills:tdd'),
  ranBash(PR),
  ranSkill('mattpocock-skills:tdd'),
]);

// A busy session, standing in for the live transcript three cases used to read. The
// short fixtures above prove each rule. This one proves they still hold when the
// markers are buried in traffic, which is the only thing the real transcript added.
//
// It needs two PRs, not one. PreToolUse fires with the call under judgement already in
// the transcript, and historyBefore() strips it, so a single PR line reads as the
// current call and leaves the review looking fresh. The second one is what makes the
// first a *previous* PR and the tdd and review markers stale.
const noise = (n) => Array.from({ length: n }, (_, i) => said(`step ${i}: reading files, running tests`));
const busy = fixture('busy-session.jsonl', [
  ...noise(12),
  said('<command-name>/mattpocock-skills:to-tickets</command-name>'),
  ...noise(20),
  ranSkill('mattpocock-skills:tdd'),
  ...noise(15),
  ranSkill('mattpocock-skills:code-review'),
  ...noise(8),
  ranBash(PR),
  ...noise(30),
  ranBash(PR),
]);

function run(payload) {
  return new Promise((resolve) => {
    const child = spawn('node', [HOOK], { stdio: ['pipe', 'pipe', 'pipe'] });
    let out = '';
    child.stdout.on('data', (c) => { out += c; });
    child.on('close', () => {
      if (!out.trim()) return resolve('ALLOW');
      try { resolve(JSON.parse(out).hookSpecificOutput.permissionDecision.toUpperCase()); }
      catch { resolve('ALLOW'); }
    });
    child.stdin.end(JSON.stringify(payload));
  });
}

const newTicket = (transcript) => ({
  tool_name: 'Write',
  transcript_path: transcript,
  tool_input: { file_path: `${REPO}/.scratch/demo/issues/01-a.md` },
});

const cases = [
  ['ticket + user typed /to-tickets', newTicket(typed), 'ALLOW'],
  ['ticket + only a REFUSED Skill call', newTicket(refused), 'DENY'],
  ['ticket + nothing', newTicket(empty), 'DENY'],
  ['ticket + hook own deny text echoed', newTicket(denyEcho), 'DENY'],

  // The busy session. Document gates are session-level, so the typed command still
  // clears one however much traffic follows it.
  ['ticket + busy session, typed long before the PR', newTicket(busy), 'ALLOW'],

  // The other two gates ask WHEN, and in the busy session both markers precede the PR.
  ['src edit + busy session, tdd predates the PR', {
    tool_name: 'Edit', transcript_path: busy,
    tool_input: { file_path: `${REPO}/API/src/Domain/X.php` },
  }, 'DENY'],
  ['gh pr create + busy session, review predates the PR', {
    tool_name: 'Bash', transcript_path: busy, tool_input: { command: PR },
  }, 'DENY'],

  ['src edit + nothing', {
    tool_name: 'Edit', transcript_path: empty,
    tool_input: { file_path: `${REPO}/API/src/Domain/X.php` },
  }, 'DENY'],
  // Pins SOURCE_DIRS covering the kit's own default layout, which uses app/ not dashboard/.
  ['src edit under app/ is gated too', {
    tool_name: 'Edit', transcript_path: empty,
    tool_input: { file_path: `${REPO}/app/src/feature/x.ts` },
  }, 'DENY'],
  ['test file edit + nothing (never gated)', {
    tool_name: 'Edit', transcript_path: empty,
    tool_input: { file_path: `${REPO}/API/tests/FooTest.php` },
  }, 'ALLOW'],
  ['gh pr create + nothing', {
    tool_name: 'Bash', transcript_path: empty, tool_input: { command: 'gh pr create --fill' },
  }, 'DENY'],
  ['gh pr create named inside a commit message', {
    tool_name: 'Bash', transcript_path: empty,
    tool_input: { command: 'git commit -m "explain gh pr create"' },
  }, 'ALLOW'],
  ['gh pr create inside an echoed payload that chains', {
    tool_name: 'Bash', transcript_path: empty,
    tool_input: { command: `echo '{"c":"x && gh pr create --fill"}' | node hook.mjs` },
  }, 'ALLOW'],

  // A quoted mention in the history must not count as a previous PR either.
  ['quoted mention in history is not a previous PR', {
    tool_name: 'Bash',
    transcript_path: fixture('quoted-history.jsonl', [
      ranSkill('mattpocock-skills:code-review'),
      ranBash(`echo '{"c":"a && gh pr create --fill"}' | node h.mjs`),
      ranBash(PR),
    ]),
    tool_input: { command: PR },
  }, 'ALLOW'],

  // Freshness: one review licenses one PR, not the whole session.
  ['2nd PR, review only before the 1st', {
    tool_name: 'Bash', transcript_path: twoPrs, tool_input: { command: PR },
  }, 'DENY'],
  ['2nd PR, fresh review after the 1st', {
    tool_name: 'Bash', transcript_path: twoPrsReviewed, tool_input: { command: PR },
  }, 'ALLOW'],
  ['retry of the identical command after reviewing', {
    tool_name: 'Bash', transcript_path: deniedThenReviewed, tool_input: { command: PR },
  }, 'ALLOW'],
  ['prose and commit-message mentions are not a PR', {
    tool_name: 'Bash', transcript_path: prosePr, tool_input: { command: PR },
  }, 'ALLOW'],

  ['src edit, tdd only before the last PR', {
    tool_name: 'Edit', transcript_path: tddBeforePr,
    tool_input: { file_path: `${REPO}/API/src/Domain/X.php` },
  }, 'DENY'],
  ['src edit, fresh tdd after the last PR', {
    tool_name: 'Edit', transcript_path: tddAfterPr,
    tool_input: { file_path: `${REPO}/API/src/Domain/X.php` },
  }, 'ALLOW'],
  ['test edit stays ungated after a PR', {
    tool_name: 'Edit', transcript_path: tddBeforePr,
    tool_input: { file_path: `${REPO}/API/tests/FooTest.php` },
  }, 'ALLOW'],

  // Document gates stay session-level: only the user can clear them.
  ['ticket after a PR, user typed it earlier', {
    tool_name: 'Write',
    transcript_path: fixture('ticket-then-pr.jsonl', [
      said('<command-name>/mattpocock-skills:to-tickets</command-name>'),
      ranBash(PR),
    ]),
    tool_input: { file_path: `${REPO}/.scratch/demo/issues/02-b.md` },
  }, 'ALLOW'],
];

let bad = 0;
for (const [name, payload, want] of cases) {
  const got = await run(payload);
  const ok = got === want;
  if (!ok) bad++;
  console.log(`${ok ? 'ok  ' : 'FAIL'}  ${want.padEnd(5)} ${name}${ok ? '' : `  -> got ${got}`}`);
}
console.log(bad === 0 ? '\nall pass' : `\n${bad} FAILED`);
process.exit(bad === 0 ? 0 : 1);
