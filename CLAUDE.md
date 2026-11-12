# LinkCash (mst-link-shortener)

A link shortener where valid visits earn money, built with Laravel. The website serves Vietnamese users, but code, comments, documentation, skills, agents, commits and PRs are all written in English. Only end-user copy shown on the website is Vietnamese.

Follow the conventions of the earlier project `mst-software/mst-shop-acc-game-v2` through the `mst-laravel-conventions` skill.

## Harness: LinkCash development

**Goal:** build and change app features consistently with the reference conventions, with tests, and verified in the browser.

**When to invoke:** for any request to build, change, fix or review app code or UI, use the `linkcash-dev-workflow` skill. Read-only questions can be answered directly. Every code change follows `mst-laravel-conventions`, and every change to files follows `git-workflow`.

**Change history:**
| Date | Change | Target | Reason |
| --- | --- | --- | --- |
| 2026-11-12 | Initial harness (page-builder, qa-reviewer, linkcash-site-workflow) for the static HTML site | All | - |
| 2026-11-12 | Moved to Laravel: replaced page-builder with laravel-builder, rewrote qa-reviewer, replaced linkcash-site-workflow with linkcash-dev-workflow, added mst-laravel-conventions, rewrote git-workflow for commitlint-style commits, switched all harness files to English | All | Project is being rewritten in Laravel; all code and docs must be English |
