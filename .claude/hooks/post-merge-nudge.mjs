#!/usr/bin/env node
// PostToolUse hook: after a PR merges, remind Claude to record it.
//
// A nudge rather than a block, because the merge already happened - there is
// nothing left to prevent. The reminder is injected into context, so Claude
// sees it without the user having to ask.

import { spawnSync } from 'node:child_process';
import { commandSegments } from './lib/shell.mjs';

function read(stream) {
  return new Promise((resolve) => {
    const chunks = [];
    stream.on('data', (c) => chunks.push(c));
    stream.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
    stream.on('error', () => resolve(''));
  });
}

function quiet() { process.exit(0); }

const raw = await read(process.stdin);
let payload;
try { payload = JSON.parse(raw); } catch { quiet(); }

if (payload?.tool_name !== 'Bash') quiet();

// Head of each segment only, and the split is quote-aware, so the string inside an
// echo or a commit message does not count as having run the command. A separator
// inside quotes used to end the segment early and hand the rest a clean head.
const command = payload?.tool_input?.command ?? '';
const mergeSegment = commandSegments(command)
  .find((segment) => /^gh\s+pr\s+merge\b/.test(segment));
if (!mergeSegment) quiet();

// --auto queues the merge behind required checks, so nothing has merged yet.
if (/\s--auto\b/.test(mergeSegment)) quiet();

// Ask GitHub rather than read gh's output. gh prints its confirmation only to a
// terminal, and a hook never has one, so the output captured here is empty even
// on a clean merge. Absence of the words "error" or "failed" was never evidence
// either: a 503 body carries neither.

// The selector has to sit before the first flag. Scanning further would read the
// digits inside --body "Fixes 76" as a pull request number.
function selectorFrom(segment) {
  const tokens = segment.split(/\s+/).slice(3).filter(Boolean);
  const first = tokens[0];
  if (!first || first.startsWith('-')) return null;
  if (/^\d+$/.test(first)) return first;
  if (/^https?:\/\/\S+\/pull\/\d+/.test(first)) return first;
  return null;
}

// Never throws and never hangs: a nudge is not worth failing a tool call over.
function isMerged(selector, cwd) {
  const args = ['pr', 'view'];
  if (selector) args.push(selector);
  args.push('--json', 'state');
  try {
    const result = spawnSync('gh', args, {
      cwd,
      timeout: 5000,
      encoding: 'utf8',
      shell: process.platform === 'win32',
    });
    if (result.status !== 0 || !result.stdout) return false;
    return JSON.parse(result.stdout)?.state === 'MERGED';
  } catch {
    return false;
  }
}

// No selector means gh would resolve the current branch, which the merge has
// usually just deleted. Staying quiet beats guessing at which pull request ran.
const selector = selectorFrom(mergeSegment);
if (!selector) quiet();

const cwd = typeof payload?.cwd === 'string' && payload.cwd ? payload.cwd : process.cwd();
if (!isMerged(selector, cwd)) quiet();

process.stdout.write(JSON.stringify({
  hookSpecificOutput: {
    hookEventName: 'PostToolUse',
    additionalContext:
      'A pull request was just merged. If it finished a feature or milestone, run the /done skill ' +
      'to record it in docs/STATUS.md. Skip it for docs-only or chore merges that change no status.',
  },
}));
process.exit(0);
