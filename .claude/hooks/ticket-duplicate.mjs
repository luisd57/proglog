#!/usr/bin/env node
// PreToolUse hook: a new ticket that restates an open one gets stopped.
//
// Creation only. Editing an existing ticket is never gated, so status flips,
// comments and re-measurements stay cheap.
//
// Scoring is the tracker's own: title against title, exact tokens, three of them
// before two tickets count as the same subject. Resolved tickets are ignored -
// filing a fresh ticket about something already shipped is a normal thing to do.
//
// The escape hatch is deliberately not a magic word. Writing a "Not a duplicate
// of" line puts the distinction in the ticket, where the next reader needs it
// anyway, rather than only in a hook's audit trail.

import { existsSync } from 'node:fs';
import { matchOpenTickets, parseTicket, relative } from './lib/tracker.mjs';

const TICKET_PATH = /(^|\/)\.scratch\/[^/]+\/issues\/[^/]+\.md$/;
const WAIVER = /^\*\*Not a duplicate of:\*\*\s*\S/m;

function read(stream) {
  return new Promise((resolve) => {
    const chunks = [];
    stream.on('data', (c) => chunks.push(c));
    stream.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    stream.on('error', () => resolve(''));
  });
}

// Any failure here must be silent: a broken hook should never block work.
function allow() { process.exit(0); }

function deny(reason) {
  process.stdout.write(JSON.stringify({
    hookSpecificOutput: {
      hookEventName: 'PreToolUse',
      permissionDecision: 'deny',
      permissionDecisionReason: reason,
    },
  }));
  process.exit(0);
}

const raw = await read(process.stdin);
let payload;
try { payload = JSON.parse(raw); } catch { allow(); }

if (payload?.tool_name !== 'Write') allow();

const file = payload?.tool_input?.file_path;
if (typeof file !== 'string' || !file) allow();

const normalized = file.replace(/\\/g, '/');
if (!TICKET_PATH.test(normalized)) allow();
if (existsSync(file)) allow();

const content = payload?.tool_input?.content;
if (typeof content !== 'string' || !content) allow();
if (WAIVER.test(content)) allow();

// Only the title is scored, so a ticket that merely cites another in its body
// does not read as a copy of it.
const proposed = parseTicket(content);
if (!proposed) allow();

const cwd = typeof payload?.cwd === 'string' && payload.cwd ? payload.cwd : process.cwd();
const matches = matchOpenTickets(cwd, proposed.title, { skip: file });
if (!matches.length) allow();

const detail = matches
  .slice(0, 3)
  .map((match) => `  ${relative(match.file, cwd)}\n` +
    `    ${match.title}\n` +
    `    Status: ${match.status}  (shared wording: ${match.overlap.join(', ')})`)
  .join('\n\n');

deny(
  `"${proposed.title}" restates an open ticket:\n\n${detail}\n\n` +
  'Read it before filing. If it is the same subject, add what you found to that ' +
  'ticket as a comment or a new acceptance criterion - editing an existing ticket ' +
  'is not gated. If it is genuinely a different problem, say so in the new ticket ' +
  'with a line reading "**Not a duplicate of:** <ticket>, <why>", which both clears ' +
  'this gate and tells the next reader what the difference is.'
);
