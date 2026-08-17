# Documentation Style

When writing or updating CLAUDE.md, .claude/rules/ files, or any project documentation:
- Be terse. Imperative sentences, not explanations.
- No code examples unless the pattern is non-obvious and can't be inferred from existing code.
- No verbose introductions or summaries.
- Write for a human skimming the file, not for completeness. If a section is long enough that
  the reader would skip it, it is too long - cut it or move it out.
- CLAUDE.md is an operating manual, not a tutorial. Every line earns its place by being something
  an agent would otherwise get wrong.
- Never duplicate information already discoverable in the codebase.
- No long checklists - only the handful of rules Claude would actually get wrong.

## Where things go

- CLAUDE.md loads every session - keep frequently-changing content (status, dates, counts) out of it. That belongs in `docs/STATUS.md`.
- Growing CLAUDE.md is the wrong move. Add a `paths:`-scoped rule in `.claude/rules/` so it loads only for matching files.
- Multi-step workflows go in `.claude/skills/`, not in a rules file - skills load on demand.
- State the reason behind a non-obvious convention. A rule with a reason survives; a bare prohibition gets "helpfully" undone.
