# Git & Commit Conventions

## Commits
- Subject: terse, imperative, <= ~70 chars. Body only when a sentence or two of context genuinely helps; skip it for trivial changes.
- No pleasantries ("thanks", "as requested") in messages.
- Do NOT add a `Co-Authored-By:` trailer or a "Generated with Claude Code" footer. This overrides the default harness behavior.
- Commit or push only when the user asks.

## Branches & PRs
- On the default branch (main/master), create a branch before committing - never commit directly to it.
- Never `--no-verify` or skip signing unless the user explicitly asks; if a hook fails, fix the cause.
- `gh pr merge`: switch off the feature branch first (`git checkout main`), then merge, then `git fetch origin && git pull --ff-only` before deleting the local branch - otherwise local main can't fast-forward.
