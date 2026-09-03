#!/usr/bin/env node
// PostToolUse hook: when a CI failure is read, name the ticket that already
// describes it.
//
// This is not a gate. "Did the session search the tracker" is satisfiable by a
// token grep, and detecting it means matching a string this hook's own output
// would contain. Doing the search is strictly better than demanding it.
//
// Scoring is title-only and exact-token. Bodies pull in too much shared
// vocabulary, and substring matching lets "result" hit "test-results".

import { runsCommand } from './lib/shell.mjs';
import { matchOpenTickets, relative } from './lib/tracker.mjs';

// Commands that read CI results. Local test runs are deliberately absent: a
// suite failing on code just written is not something the tracker would know.
//
// `gh pr checks` is absent for a different reason. It prints a job name, a
// status, a duration and a URL, which after the length filter leaves about one
// word a ticket title could ever contain. It could not reach MIN_OVERLAP, so
// watching it only implied a coverage this hook never had.
const CI_READ = /^gh\s+run\s+(view|watch)\b/;

function read(stream) {
  return new Promise((resolve) => {
    const chunks = [];
    stream.on('data', (c) => chunks.push(c));
    stream.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    stream.on('error', () => resolve(''));
  });
}

// Any failure here must be silent: a broken hook should never block work.
function bail() { process.exit(0); }

// Only the head of each command segment counts, and the split is quote-aware, so
// the pattern does not fire on the string inside an echo, a commit message or a
// grep pattern. See lib/shell.mjs.

const raw = await read(process.stdin);
let payload;
try { payload = JSON.parse(raw); } catch { bail(); }

if (payload?.tool_name !== 'Bash') bail();

const command = payload?.tool_input?.command ?? '';
if (typeof command !== 'string' || !runsCommand(command, CI_READ)) bail();

const resp = payload?.tool_response;
const output = typeof resp === 'string'
  ? resp
  : [resp?.stdout, resp?.stderr].filter((s) => typeof s === 'string').join('\n');
if (!output.trim()) bail();

const cwd = typeof payload?.cwd === 'string' && payload.cwd ? payload.cwd : process.cwd();
const matches = matchOpenTickets(cwd, output);
if (!matches.length) bail();

const detail = matches
  .slice(0, 3)
  .map((m) => `  ${relative(m.file, cwd)}\n` +
    `    ${m.title}\n` +
    `    Status: ${m.status}  (matched on: ${m.overlap.join(', ')})`)
  .join('\n\n');

const reason = `This failure output overlaps an open ticket that already describes it:\n\n` +
  `${detail}\n\n` +
  `Read the ticket before diagnosing. It may already carry the root cause, the ` +
  `reproduction, and what a fix has to prove - re-deriving that from logs wastes ` +
  `the work and usually misses detail the ticket has. If it turns out not to be ` +
  `the same problem, say so and carry on.`;

process.stdout.write(JSON.stringify({
  decision: 'block',
  reason,
  systemMessage: `Failure output matches ${matches.length} open ticket(s) - Claude was pointed at them.`,
}));
process.exit(0);
