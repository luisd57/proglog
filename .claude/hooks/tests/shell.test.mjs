// Unit tests for the quote-aware splitter. Expected segment lists are written out
// by hand, never derived by re-running the splitter.
import { commandSegments, runsCommand } from '../lib/shell.mjs';

const SPLITS = [
  ['single command', 'gh pr create --fill', ['gh pr create --fill']],
  ['and', 'a && b', ['a', 'b']],
  ['or', 'a || b', ['a', 'b']],
  ['pipe', 'a | b', ['a', 'b']],
  ['semicolon', 'a ; b', ['a', 'b']],
  ['newline', 'a\nb', ['a', 'b']],
  ['a lone ampersand is not a separator', 'a & b', ['a & b']],

  // The bug this file exists for.
  ['separators inside single quotes do not split',
    `echo '{"c":"x && gh pr merge 76"}' | node hook.mjs`,
    [`echo '{"c":"x && gh pr merge 76"}'`, 'node hook.mjs']],
  ['separators inside double quotes do not split',
    'git commit -m "ran a && b" && git push',
    ['git commit -m "ran a && b"', 'git push']],
  ['a pipe inside quotes does not split',
    `grep -E "foo|bar" file`,
    [`grep -E "foo|bar" file`]],

  ['single quotes keep a backslash literal', `echo 'a\\' ; b`, [`echo 'a\\'`, 'b']],
  ['an escaped separator outside quotes is literal', 'echo a \\&& b', ['echo a \\&& b']],
  ['an escaped quote does not open a string', 'echo \\" && b', ['echo \\"', 'b']],
  ['an unterminated quote swallows the rest', `echo 'a && b`, [`echo 'a && b`]],
  ['quotes nested in the other kind', `echo "it's fine" && b`, [`echo "it's fine"`, 'b']],
];

const RUNS = [
  ['real invocation', 'gh pr create --fill', true],
  ['second in a chain', 'git push && gh pr create --fill', true],
  ['quoted, with separators inside', `echo '{"c":"x && gh pr create"}' | node h.mjs`, false],
  ['inside a commit message', 'git commit -m "explain gh pr create"', false],
  ['inside a commit message with a chain', 'git commit -m "run a && gh pr create"', false],
  ['as a grep pattern', 'grep -r "gh pr create" .', false],
  ['named in prose after a pipe', 'echo hi | grep "gh pr create"', false],
];

let bad = 0;
const same = (a, b) => a.length === b.length && a.every((v, i) => v === b[i]);

for (const [name, input, want] of SPLITS) {
  const got = commandSegments(input);
  const ok = same(got, want);
  if (!ok) bad++;
  console.log(`${ok ? 'ok  ' : 'FAIL'}  split   ${name}${ok ? '' : `\n        want ${JSON.stringify(want)}\n        got  ${JSON.stringify(got)}`}`);
}

for (const [name, input, want] of RUNS) {
  const got = runsCommand(input, /^gh\s+pr\s+create\b/);
  const ok = got === want;
  if (!ok) bad++;
  console.log(`${ok ? 'ok  ' : 'FAIL'}  runs    ${String(want).padEnd(5)} ${name}${ok ? '' : `  -> got ${got}`}`);
}

console.log(bad === 0 ? '\nall pass' : `\n${bad} FAILED`);
process.exit(bad === 0 ? 0 : 1);
