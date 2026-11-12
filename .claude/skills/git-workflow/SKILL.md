---
name: git-workflow
description: How to create branches, commit and open PRs in this repo (LinkCash, a Laravel app, mst-software/mst-link-shortener). Use for EVERY task that changes files (feature, fix, refactor, docs, chore) before the first edit and at every commit.
---

# Git workflow

Commit rules mirror the reference project `mst-shop-acc-game-v2`, where commits are checked by commitlint (Husky `commit-msg` hook).

## 1. One task = one branch

Before editing anything:

```
git checkout main && git pull
git checkout -b <type>/<short-kebab-description>
```

- Branch type = commit type: `feat/`, `fix/`, `refactor/`, `docs/`, `chore/`, `test/`, `perf/`, `style/`.
- Name: 2-5 English words, lowercase, hyphens: `feat/link-stats`, `fix/payout-rounding`.
- Never commit on `main`. Never reuse a merged branch for a new task.
- If the task depends on an unmerged branch, branch from that one and say so in the PR.

## 2. Commit as small as possible

- One logical change per commit: a migration, a model, one controller action, one fix. If the message needs "and", split it.
- Stage explicitly (`git add <files>`), never `git add -A` blindly. Run `git status`, `git diff --staged` and `git log --oneline -10` first, and match the scopes recent commits use.
- Commit as you go; do not batch a whole task into one commit.
- Renames/moves in their own commit, separate from edits.
- Run `vendor/bin/pint --dirty` and the relevant tests before committing code.

## 3. Message format

```
type(scope): subject
```

Enforced by commitlint:

- `type` is one of `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`, `revert`, `ci`; lowercase.
- `subject` is lowercase, non-empty, no trailing period; the whole header is at most 100 characters.
- `scope` is optional, lowercase, and only used when it adds clarity: `feat(link): ...`, `fix(admin): ...`, `test(payout): ...`.
- One line only: no body, no footer.
- English only, no emoji. Imperative mood, say what changed, not how.
- Never add `Co-Authored-By`, "Generated with", or any AI attribution.
- If a change mixes concerns (for example a fix and a refactor), split it into separate commits; if unsure how to frame it, ask the user.

Good: `feat(link): add click tracking`, `fix(admin): correct payout total`, `chore: bump laravel to 12.5`.
Bad: `update stuff`, `Fix: Bug.`, `feat: add stats and fix navbar and update css`.

## 4. Pull requests

- One PR per branch, opened only when the task is done. Title follows the commit style (English). Body: 2-5 short English bullets (what, why, how it was verified, anything not verified).
- Push with `git push -u origin HEAD`. Do not force-push shared branches.
- PRs target `main` of `mst-software/mst-link-shortener` and are merged on GitHub as merge commits. Do not merge unless the user says to. When asked to merge several PRs, merge in dependency order and resolve conflicts by keeping both sides.
- For UI changes, verify in a browser (desktop and mobile width) before opening the PR and say so in the body.
- Never commit: `.env*`, `vendor/`, `node_modules/`, `public/build`, `storage` contents, `.playwright-cli/` and `.playwright/` output (may contain credentials), debug screenshots or recordings, personal paths, IPs or keys.

## 5. Checklist before every commit

1. On a task branch (not `main`).
2. Only the files of this change are staged.
3. Message matches `type(scope): subject`, English, lowercase, at most 100 characters, one line.
4. Pint is clean and the relevant tests pass.
5. No secrets or personal data in the diff.
