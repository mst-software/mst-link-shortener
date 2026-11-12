---
name: linkcash-dev-workflow
description: Orchestrates adding or changing features in the LinkCash Laravel app: create a branch, laravel-builder implements with tests, qa-reviewer checks tests, style and the browser, fix findings, then commit. Use for any request to build, change, fix or review app code or UI, including follow-ups such as "redo", "update", "fix this part", "re-check page X" or "add a feature". Do not use for read-only questions.
---

# LinkCash development workflow

**Execution mode:** sequential subagents (builder, then QA). The two steps depend on each other and the scale is small, so no workflow script or agent team is needed.

## Step 0: Detect context

- New request: start at step 1.
- Follow-up on earlier work (already on the task branch): skip step 1 and give `laravel-builder` only the part to change.
- Only a re-check is requested: go straight to step 3.

## Step 1: Prepare

Follow the `git-workflow` skill: create `<type>/<short-description>` from an up-to-date `main`. Never work on `main`.

## Step 2: Build

Call `laravel-builder` (`Agent` with `subagent_type: "laravel-builder"`). The prompt states the goal, affected areas, acceptance criteria and any user constraints. It returns the changed files and the test results.

## Step 3: Verify

Call `qa-reviewer` with the changed files and what to check. It runs tests and Pint, reviews the diff against `mst-laravel-conventions`, and checks the UI in a browser when pages changed.

## Step 4: Fix loop

If there are "blocking" or "should fix" findings, pass exactly those to `laravel-builder`, then call `qa-reviewer` again. At most 2 rounds. If issues remain, report them to the user instead of looping.

## Step 5: Commit

Small commits, one logical change each, following `git-workflow` (`type(scope): subject`). Open a PR only when the user asks or the task is complete, and state what was and was not verified.

## Error handling

- An agent fails: retry once with a clearer prompt; if it fails again, tell the user which part is missing.
- The app or browser cannot be started for QA: say so in the final report, and report which checks (tests, Pint, static review) did run.

## Test scenarios

- **Normal:** "add click statistics to a short link" leads to branch `feat/link-stats`, then the builder adds migration, model, controller action, form request and Pest tests, QA confirms tests, Pint and the page, then small commits.
- **Failure:** QA finds a missing row lock on a balance update, the builder fixes it and adds a test, QA re-checks and it passes, then commit.
