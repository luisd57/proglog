// Reading the local markdown issue tracker, and scoring text against it.
//
// Scoring is title-only and exact-token. Bodies pull in too much shared
// vocabulary, and substring matching lets "result" hit "test-results".

import { readFileSync, readdirSync, existsSync, statSync } from 'node:fs';
import { join } from 'node:path';

export const SCRATCH = '.scratch';

// Distinct title tokens that must overlap before two things are called the same.
// Three clears the shared vocabulary floor ("dashboard", "e2e") that otherwise
// matches half the tracker.
export const MIN_OVERLAP = 3;

const STOPWORDS = new Set([
  'the', 'and', 'for', 'from', 'into', 'with', 'that', 'this', 'when', 'then',
  'than', 'them', 'they', 'their', 'what', 'which', 'while', 'been', 'have',
  'has', 'was', 'were', 'are', 'its', 'not', 'but', 'all', 'any', 'one', 'two',
  'make', 'made', 'stop', 'keep', 'over', 'only', 'more', 'most', 'some',
]);

export function tokenize(text) {
  return (String(text ?? '').toLowerCase().match(/[a-z][a-z0-9-]{2,}/g) ?? [])
    .filter((token) => token.length >= 4 && !STOPWORDS.has(token));
}

export function ticketFiles(root) {
  const base = join(root, SCRATCH);
  if (!existsSync(base)) return [];
  const out = [];
  for (const feature of readdirSync(base).sort()) {
    const dir = join(base, feature, 'issues');
    let entries;
    try {
      if (!statSync(dir).isDirectory()) continue;
      entries = readdirSync(dir);
    } catch { continue; }
    for (const name of entries.sort()) {
      if (name.endsWith('.md')) out.push(join(dir, name));
    }
  }
  return out;
}

/** Title and status of one ticket, or null when the file is unreadable or unshaped. */
export function readTicket(file) {
  let body;
  try { body = readFileSync(file, 'utf8'); } catch { return null; }
  return parseTicket(body, file);
}

export function parseTicket(body, file = null) {
  const status = body.match(/^\*\*Status:\*\*\s*(.+)$/m)?.[1]?.trim();
  const title = body.match(/^#\s+(.+)$/m)?.[1]?.trim();
  if (!status || !title) return null;
  return { file, title, status, open: !/^resolved\b/i.test(status) };
}

/**
 * Open tickets whose titles overlap `text` by at least `floor` distinct tokens,
 * best first. Path breaks ties so the same input always names the same ticket
 * first, whatever order the filesystem hands the directory back in.
 */
export function matchOpenTickets(root, text, { floor = MIN_OVERLAP, skip = null } = {}) {
  const seen = new Set(tokenize(text));
  if (!seen.size) return [];

  const matches = [];
  for (const file of ticketFiles(root)) {
    if (skip && file === skip) continue;
    const ticket = readTicket(file);
    if (!ticket || !ticket.open) continue;

    const overlap = [...new Set(tokenize(ticket.title))].filter((token) => seen.has(token));
    if (overlap.length >= floor) {
      matches.push({ ...ticket, score: overlap.length, overlap });
    }
  }

  matches.sort((a, b) => b.score - a.score || String(a.file).localeCompare(String(b.file)));
  return matches;
}

export function relative(file, root) {
  return String(file).replace(root, '').replace(/^[/\\]/, '');
}
