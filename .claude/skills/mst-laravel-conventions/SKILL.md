---
name: mst-laravel-conventions
description: Mandatory Laravel coding conventions for this project, taken from the earlier project mst-software/mst-shop-acc-game-v2 (Laravel 12, PHP 8.4, Pest 4, Pint, Tailwind 4, Blade). Use whenever creating or editing PHP, routes, controllers, form requests, models, migrations, services, enums, Blade views, Tailwind CSS or tests, whenever unsure how to write something, and when the user says "like the old project" or "like mst-shop".
---

# MST Laravel conventions (follow mst-shop-acc-game-v2)

This project is built with Laravel and must use the **same style** as the reference project `mst-software/mst-shop-acc-game-v2`, so everyone on the team can read each other's code. When unsure, read the reference code instead of guessing:

```bash
gh api repos/mst-software/mst-shop-acc-game-v2/contents/<path> --jq .content | base64 -d
```

The reference repo is private and needs an authenticated `gh`. Copy its structure and style only, never its shop/game business logic.

## Language policy

- **English everywhere**: code, identifiers, comments, PHPDoc, docs, skills, agents, commit messages, PR text, test names, config keys, route names, migration and column names.
- **The UI is English too**: Blade text, validation messages, notifications, emails and flash messages are all English. No Vietnamese anywhere in the repo. When converting existing static pages that contain Vietnamese copy, translate it to natural English.
- Use English URL slugs and route names.

## Stack

PHP 8.4, Laravel 12, Pest 4 (PHPUnit 12), Laravel Pint, Tailwind CSS 4 with Vite, Blade. Do not change dependencies or add new top-level folders without approval.

## General rules

1. **Read a sibling file first.** For a new controller, open a neighbouring controller; for a new form request, a neighbouring form request. Always follow the newer generation of code.
2. **Create files with `php artisan make:...`** and `--no-interaction`. A new model comes with a factory and a seeder.
3. **Use Laravel Boost MCP when available**: `search-docs` before writing Laravel, Pest or Tailwind code (it returns version-correct docs), and `list-artisan-commands` to check command options.
4. **Create no documentation files** unless the user asks. Keep replies concise.
5. **No throwaway verification scripts or tinker** when a test already proves the behaviour.
6. **Every change needs a test.** Write or update a test, then run the smallest relevant run: `php artisan test --compact --filter=...`. Never delete a test without approval.
7. **Run `vendor/bin/pint --dirty` before finishing.** Do not run `pint --test`.

## PHP

- Always use curly braces for control structures, even one-liners.
- Explicit return types and parameter types everywhere.
- Constructor property promotion; no empty `__construct()`.
- Enum cases are TitleCase (`FixedPrice`); give enums a `label(): string` for display text.
- Prefer PHPDoc over inline comments; comment inside code only when the logic is genuinely complex. Add array shapes in PHPDoc when useful.
- Descriptive names (`isRegisteredForDiscounts`, not `discount`).
- Pint follows the reference `pint.json`: `laravel` preset, alphabetical imports, no unused imports, trailing commas in multiline arrays, arguments and parameters.

## Laravel

- **Validation:** always a FormRequest with `rules()`, `attributes()` and custom messages where needed. Check neighbouring requests for array vs pipe-string rules. Never validate inline in a controller.
- **Database:** Eloquent and `Model::query()`; avoid `DB::` (except `DB::transaction`). Typed relationships, eager loading (`with`) to prevent N+1, reusable query logic in `scopeXxx(Builder $query): Builder`.
- **New models:** `casts()` method (not `$casts`), `HasFactory`, return types on every relationship. Details in `references/eloquent.md`.
- **Migrations:** when modifying a column, restate every previous attribute or it is dropped.
- **Config:** never call `env()` outside `config/`; use `config('...')`. Admin-editable settings live in the `settings` table via `ConfigHelper::getConfig/setConfig` (cached 1 hour); env-only config goes through `app/Config/AppConfig.php`.
- **Routes:** named routes and `route()`. Split files: `routes/web.php`, `routes/admin.php` (middleware `admin` + `admin.2fa`), `routes/console.php`.
- **Bootstrap:** register middleware, exceptions and routing in `bootstrap/app.php` (Laravel 12 has no `Http/Kernel.php`). Console commands in `app/Console/Commands` load automatically.
- **Heavy work** goes in `ShouldQueue` jobs. APIs use Eloquent API Resources and versioning unless existing routes do not. JSON controller responses use the `ApiResponse` trait (`success`, `error`, `systemError`).
- **Authorization:** use built-in gates, policies and middleware.

## Directory layout

User-facing controllers are flat in `app/Http/Controllers/` (plus subfolders such as `Auth/`, `User/`). Admin controllers live in `Admin/`, grouped by domain. Heavy business logic goes in `app/Services/`, enums in `app/Enums/`, traits in `app/Traits/`, helpers in `app/Helpers/`, class-based Blade components in `app/View/Components/`.

## Blade and styling

- Main layout `resources/views/layouts/app.blade.php`; admin uses `admin/layouts/master.blade.php`.
- Reuse components in `resources/views/components/` before creating new ones.
- Tailwind 4: follow existing classes and patterns, check `search-docs` for syntax. After CSS/JS changes the user needs `npm run build` or `npm run dev` to see them.
- Blade formatting uses Prettier (`prettier-plugin-blade`, `prettier-plugin-tailwindcss`); `.bladeformatter.json`: indent 2, line length 120.
- `.editorconfig`: UTF-8, LF, 4-space indent (YAML 2), final newline.

## Tests (Pest)

- Create with `php artisan make:test --pest <Name>` (feature by default, `--unit` for unit tests).
- Build data with factories; check existing factory states first.
- Use `RefreshDatabase`. Mirror domains in test folders (`tests/Feature/Admin/...`).

## Money and balances

This project handles earnings and payouts, so every balance change must follow the ledger pattern in `references/wallet-ledger.md`: `DB::transaction`, row lock, and a `Transaction` row in the same transaction. If the `action` type or lock scope is unclear, ask the user.

## Working with the user

- State assumptions. If there are several readings, present them instead of silently picking one.
- Keep it minimal: no features, abstractions or configuration that were not requested.
- Make surgical changes: every changed line must trace back to the request. Mention unrelated dead code, do not delete it.
- For multi-step work, state a short plan where each step has a way to verify it.
