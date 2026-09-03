#!/usr/bin/env node
// PreToolUse hook: some work has to go through its skill first.
//
// Three gates, all session-level. They check that the skill was loaded, not that it
// was obeyed, and that gap does not close: a hook cannot see whether the test really
// came first. Nothing downstream can either: under squash merges with one commit per
// review round, no red/green pair survives anywhere in the history, so the ordering is
// invisible even after the fact.
//
// So these are generative, not enforcement. What they buy is the skill's guidance
// being in context at the moment the work starts, which is where it changes what gets
// written. Verifying the result is a separate job and belongs to the pipeline. Say
// that in the deny text rather than implying the hook checked something it cannot.
//
// Requires the mattpocock-skills plugin. Every gate below names one of its skills, and
// nothing else clears them, so without it they are permanent denials.
//
//   new ticket / spec / ADR   ->  the skill that writes that kind of document
//   editing implementation    ->  tdd
//   gh pr create              ->  code-review
//
// Creating a document is gated, editing an existing one is not, so status flips
// and typo fixes stay cheap. Test files are never gated: writing the test is the
// step TDD wants to be frictionless.
//
// "Was the skill used" reads the transcript for the Skill tool's own input
// shape rather than the bare skill name, because this hook's deny text lands in
// the transcript and would otherwise satisfy the next check.
//
// The two gates Claude can satisfy on its own - tdd and code-review - also ask
// WHEN. Both reset at each pull request, so one invocation licenses one PR's work
// rather than the whole session. The document gates stay session-level, since only
// the user can clear those and re-running them is their keystrokes, not Claude's.

import { existsSync, readFileSync } from 'node:fs';
import { runsCommand } from './lib/shell.mjs';

// userOnly marks skills carrying disable-model-invocation, which Claude cannot
// invoke for itself. Those denials have to end in "ask the user", not "invoke it".
// Several skills legitimately write the same path: to-tickets breaks a known plan
// into build slices, wayfinder charts an unknown one into decision tickets. Any
// listed skill clears the gate.
const DOC_GATES = [
  {
    pattern: /(^|\/)\.scratch\/[^/]+\/issues\/[^/]+\.md$/,
    skills: ['mattpocock-skills:to-tickets', 'mattpocock-skills:wayfinder'],
    noun: 'ticket',
    userOnly: true,
  },
  {
    pattern: /(^|\/)\.scratch\/[^/]+\/spec\.md$/,
    skills: ['mattpocock-skills:to-spec'],
    noun: 'spec',
    userOnly: true,
  },
  {
    pattern: /(^|\/)docs\/adr\/[^/]+\.md$/,
    skills: ['mattpocock-skills:domain-modeling'],
    noun: 'ADR',
  },
];

// Edit this to match the project's top-level deployable directories. It is the one
// line in this file that is not portable, and it fails open: a directory missing from
// the list means the TDD gate never fires there and nothing reports it.
const SOURCE_DIRS = ['API', 'app', 'dashboard', 'landing', 'web'];

const SOURCE = new RegExp(`(^|\\/)(${SOURCE_DIRS.join('|')})\\/src\\/`);
const TEST_FILE = /(\.spec\.|\.test\.|(^|\/)tests?\/)/;

const TDD = 'mattpocock-skills:tdd';
const REVIEW = 'mattpocock-skills:code-review';

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

// A skill starts one of two ways and the transcript records them differently.
// The user typing /skill leaves <command-name>/skill</command-name>; Claude calling
// it leaves the Skill tool's input shape. Miss the first and a userOnly gate can
// never open, since the user is the only one allowed to start those.
function readTranscript(transcript) {
  if (typeof transcript !== 'string' || !existsSync(transcript)) return null;
  try { return readFileSync(transcript, 'utf8'); } catch { return null; }
}

function lastIndexOf(body, pattern) {
  const re = new RegExp(pattern.source, pattern.flags.includes('g') ? pattern.flags : `${pattern.flags}g`);
  let last = -1;
  let match;
  while ((match = re.exec(body)) !== null) {
    last = match.index;
    if (match[0] === '') re.lastIndex += 1;
  }
  return last;
}

// Where the skill was last started, or -1. Returns a position rather than a
// boolean so callers can ask whether it came before or after something else.
function skillIndex(body, skill, userOnly = false) {
  const escaped = skill.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  // Anchored on the tag, not the bare name: this hook's deny text names the skill
  // with a leading slash and would otherwise satisfy the next check.
  const typed = lastIndexOf(body, new RegExp(`command-name>\\s*/${escaped}\\s*<`));

  // A disable-model-invocation skill refuses Claude's call and the refusal still
  // writes the input shape, so a userOnly gate must not accept it.
  const called = userOnly ? -1 : lastIndexOf(body, new RegExp(`"skill"\\s*:\\s*"${escaped}"`));

  return Math.max(typed, called);
}

// The transcript already holds the call being judged, so "the last PR" has to mean
// the last one before this call rather than this call itself.
function historyBefore(body, currentCommand) {
  if (typeof currentCommand !== 'string' || !currentCommand) return body;
  const escaped = JSON.stringify(currentCommand).slice(1, -1);
  const at = body.lastIndexOf(escaped);
  return at === -1 ? body : body.slice(0, at);
}

// Only a real Bash command counts. "gh pr create" written in prose, in a commit
// message, or quoted out of this file must not read as a pull request.
function lastPrCreateIndex(body) {
  const re = /"command"\s*:\s*"((?:[^"\\]|\\.)*)"/g;
  let last = -1;
  let match;
  while ((match = re.exec(body)) !== null) {
    let command;
    try { command = JSON.parse(`"${match[1]}"`); } catch { continue; }
    if (runsCommand(command, /^gh\s+pr\s+create\b/)) last = match.index;
  }
  return last;
}

const raw = await read(process.stdin);
let payload;
try { payload = JSON.parse(raw); } catch { allow(); }

const tool = payload?.tool_name;
const transcript = payload?.transcript_path;

// Only the head of each command segment counts, and the split is quote-aware, so
// the string inside an echo, a commit message or a grep pattern is not a command.
// See lib/shell.mjs.

if (tool === 'Bash') {
  const command = payload?.tool_input?.command ?? '';
  if (!runsCommand(command, /^gh\s+pr\s+create\b/)) allow();

  const body = readTranscript(transcript);
  if (body === null) allow();

  const history = historyBefore(body, command);
  const reviewed = skillIndex(history, REVIEW);
  const previousPr = lastPrCreateIndex(history);
  if (reviewed > previousPr) allow();

  deny(
    previousPr === -1
      ? `Opening a pull request goes through ${REVIEW}, which has not run in this session. ` +
        'Only that exact skill clears this gate - the bundled /code-review does not. ' +
        'Invoke it, act on what it finds, then create the PR.'
      : `${REVIEW} last ran before the previous pull request, so it has not seen these changes. ` +
        'Each PR gets its own review. Invoke it, act on what it finds, then create this one.'
  );
}

if (tool !== 'Write' && tool !== 'Edit') allow();

const file = payload?.tool_input?.file_path;
if (typeof file !== 'string' || !file) allow();
const normalized = file.replace(/\\/g, '/');

const body = readTranscript(transcript);
if (body === null) allow();

// Document gates apply to creation only, and only via Write.
if (tool === 'Write' && !existsSync(file)) {
  const gate = DOC_GATES.find((candidate) => candidate.pattern.test(normalized));
  if (gate) {
    if (gate.skills.some((skill) => skillIndex(body, skill, gate.userOnly) >= 0)) allow();
    const commands = gate.skills.map((skill) => `/${skill}`).join(' or ');
    deny(
      gate.userOnly
        ? `New ${gate.noun} files go through ${commands}, and neither has run in this session. ` +
          `Those skills are user-invocable only, so you cannot start one yourself: stop and ask the ` +
          `user to run the one that fits. Do not hand-write the ${gate.noun} instead. ` +
          `Editing an existing ${gate.noun} is not gated - only creating a new one.`
        : `New ${gate.noun} files go through ${commands}, which has not run in this session. ` +
          `Invoke it and let it write the file. ` +
          `Editing an existing ${gate.noun} is not gated - only creating a new one.`
    );
  }
}

// Implementation gate. Tests are exempt so the first TDD step is never blocked.
if (SOURCE.test(normalized) && !TEST_FILE.test(normalized)) {
  const started = skillIndex(body, TDD);
  const previousPr = lastPrCreateIndex(body);
  if (started > previousPr) allow();

  deny(
    previousPr === -1
      ? `Implementation changes start by loading ${TDD}, which has not run in this session. ` +
        'Only that exact skill clears this gate, and the gate checks that its guidance is in ' +
        'context, not that you followed it. Load it, then write the failing test first. ' +
        'Test files are not gated, so you can start there right now.'
      : `${TDD} last ran before the previous pull request, so its guidance belongs to work ` +
        'already shipped. Load it again for this change. Test files are not gated, so you can ' +
        'start there right now.'
  );
}

allow();
