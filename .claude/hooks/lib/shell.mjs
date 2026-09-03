// Splitting a command line into the commands it actually runs.
//
// Naive splitting on the separators alone breaks inside quotes, so an echoed or
// committed string containing "&& gh pr create" lands a segment boundary in the
// middle of the quotes and the next segment reads as a real invocation. This
// walks the string instead and only breaks outside quotes.
//
// Shell-approximate on purpose. It knows quoting and backslash escapes, which is
// what tells a command from a string that mentions one. It does not know
// substitution, heredocs or subshells, and does not need to.

const SEPARATOR_PAIRS = new Set(['&&']);

export function commandSegments(command) {
  if (typeof command !== 'string' || !command) return [];

  const segments = [];
  let current = '';
  let quote = null;

  for (let index = 0; index < command.length; index += 1) {
    const char = command[index];
    const next = command[index + 1];

    if (quote) {
      current += char;
      // Single quotes take everything literally, backslash included.
      if (char === '\\' && quote === '"' && next !== undefined) {
        current += next;
        index += 1;
      } else if (char === quote) {
        quote = null;
      }
      continue;
    }

    if (char === '\\' && next !== undefined) {
      current += char + next;
      index += 1;
      continue;
    }

    if (char === "'" || char === '"') {
      quote = char;
      current += char;
      continue;
    }

    if (char === '|' || char === ';' || char === '\n') {
      if (char === '|' && next === '|') index += 1;
      segments.push(current);
      current = '';
      continue;
    }

    if (SEPARATOR_PAIRS.has(char + next)) {
      index += 1;
      segments.push(current);
      current = '';
      continue;
    }

    current += char;
  }

  segments.push(current);
  return segments.map((segment) => segment.trim());
}

/** Whether any command in the line starts with something matching `pattern`. */
export function runsCommand(command, pattern) {
  return commandSegments(command).some((segment) => pattern.test(segment));
}
