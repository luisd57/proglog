# Git & Commit Conventions

## Commits
- Subject: terse, imperative, <= ~70 chars. Body only when a sentence or two of context genuinely helps; skip it for trivial changes.
- No pleasantries ("thanks", "as requested") in messages.
- Do NOT add a `Co-Authored-By:` trailer or a "Generated with Claude Code" footer. This overrides the default harness behavior.
- Commit or push only when the user asks.

## Branches & PRs
- On the default branch (main/master), create a branch before committing - never commit directly to it.
- `git checkout -b`: confirm you are on `main` first. A branch cut from another feature branch inherits that branch's commits, and the PR carries them even when your own commit is clean.
- After opening a PR, check `gh pr diff --name-only`. `git diff --stat` before committing only proves your commit is clean; the PR diff is against the base branch, and that is what merges.
- Never `--no-verify` or skip signing unless the user explicitly asks; if a hook fails, fix the cause.
- `gh pr merge`: switch off the feature branch first (`git checkout main`), then merge, then `git fetch origin && git pull --ff-only` before deleting the local branch - otherwise local main can't fast-forward.
