# Member dashboard, view counting and ads: design

Status: draft for approval. Date: 2026-11-13. Language: English (project rule: code, docs and UI are all English).

## 1. Intent

LinkCash pays members for valid views on their short links. The member area (the pages after login) must work like the reference dashboard `my.link4m.com/member/*`, and every number on it must come from real data. The most important part is **view counting**: a view is paid only when the visitor passes the rules already published on the Publisher Rates page. On top of that, the visitor pages need **ad slots** that an admin can manage.

Why it matters: the product is a Laravel source kit that buyers run on ordinary cPanel hosting (see `docs/du-an-rut-gon-link-kiem-tien.md`). Buyers only receive money from ad networks when traffic is clean, so a view filter that is hard to fake is the main selling point.

## 2. Goals

1. Member area with every page of the reference: Overview, Notifications, Statistics, Top Links, Links (all and hidden), Withdrawals, Tools (Quick Link, Mass Shrinker, Full Page Script, Developers API), Referrals, Settings (Profile, Change Password, Change Email), Support.
2. Real accounts: register, sign in, forgot password, change email, change password (the current auth pages are demo-only).
3. Link creation (form on every member page, mass shrinker, quick link URL, API) with alias, level 1 to 3 and a visitor page `/{alias}`.
4. **View counting, Approach A:** server-validated multi-step flow. A view is recorded only at the last step and only if every rule passes.
5. Earnings, pending and available balance, referral commission, withdrawal requests, all through the ledger pattern.
6. Ad slots per step, managed by an admin, network-agnostic (paste any ad code).
7. A minimal admin area to operate the above (rates, ad slots, settings, withdrawals, blocked networks, fraud flags, notifications).
8. All JavaScript uses jQuery. All UI copy is English.

## 3. Non-goals

- Real money transfers: withdrawals are approved and paid by hand by an admin.
- Telegram or email notifications, 2FA, Google sign in, custom domains, multi-language, blog or CMS in admin.
- A web installer and cPanel packaging (later phase of the product roadmap).
- Third-party anti-fraud services (Approach C). The design leaves a hook for them.
- DataTables, CKEditor, Highcharts Stock: we use a light chart library only.

## 4. Anti-goals (a failure even if it "works")

- A view is paid that breaks a published rule.
- A balance changes without a `Transaction` row in the same database transaction.
- The server trusts a client timer, a client-supplied country or a client-supplied amount.
- The visitor page can be skipped by editing HTML or calling the last endpoint directly.
- The member dashboard numbers disagree with the ledger.

## 5. Constraints

- Laravel 12, PHP 8.4, Pest, Pint, Tailwind 4 and Vite for the public site, existing conventions in `mst-laravel-conventions` and the Boost guidelines.
- Must run on shared hosting: **no Redis, no long-running worker**. Cache, queue and session use the database driver; the queue is drained by cron (`schedule:run` every minute).
- The member and admin areas use their own Blade layouts (AdminLTE-style sidebar). They are separate from the public layout and from the auth layout.
- Money is stored as integers in **micro-USD** (1 USD = 1,000,000) to avoid float errors. The rate table is USD per 1,000 views, so one view is rate x 1,000 micro-USD.
- Every rule parameter (dwell seconds, unique window, review days, minimum withdrawal) lives in the `settings` table and is editable in admin. Defaults: 10 s, 24 h, 3 days, 5 USD.

## 6. Approaches considered

| | A: server-validated steps | B: client timer, one callback | C: A plus external anti-fraud |
|---|---|---|---|
| Matches published policy | Yes | No (timer can be edited) | Yes |
| Hard to fake | High | Low | Highest |
| Cost and effort | Medium to high | Low | A plus paid service |

**Chosen: A** (confirmed by the product owner). B is dropped because it contradicts the rule "time measured on the server". C can be added later behind a `VisitorRiskChecker` interface.

## 7. Visitor flow and view counting (core)

```
GET /{alias}                -> create visit session, set cookie, redirect to step 1
GET /go/{session}/{step}    -> show step page (ads + countdown), issue step token
POST /go/{session}/{step}   -> submit token + fingerprint; server checks dwell time
last step                   -> Turnstile token verified server-side
POST /go/{session}/finish   -> run all checks, record the view, return destination URL
```

**Visit session** (`visit_sessions`): ULID id, link, IP, user-agent hash, country, level, current step, `step_issued_at`, status, `expires_at` (30 min). Everything the rules need is stored on the server, so the client cannot change it.

**Step token:** HMAC-SHA256 (app key) over session id, step number, IP, user-agent hash, `issued_at` and a nonce. One use only. The server rejects a `next step` request that arrives before `issued_at + min_dwell_seconds`, arrives with a bad or reused token, or comes from a different IP or user-agent.

**Fingerprint:** the jQuery step script posts a hash built from stable browser properties (screen, time zone, language, canvas hash). A request without JavaScript can never get a token, so "JavaScript enabled" is enforced by design. A cookie set at `GET /{alias}` must come back (cookie enabled).

**Checks at `finish`** (a failed check still redirects the visitor to the destination, but the view is stored as invalid with a reason and earns nothing):

1. All steps of the link level done in order, each after the dwell time.
2. Captcha (Cloudflare Turnstile) verified with the server secret.
3. Unique: no paid view for the same link by the same IP plus fingerprint in the unique window (24 h). Backed by the `view_dedup` table with a unique key and `expires_at`, so it works without Redis.
4. Network: the IP is not in `blocked_networks` (datacenter, VPN, proxy ranges, imported from a list and refreshed by cron).
5. Bot: user-agent denylist and missing-JavaScript or cookie signals.
6. Country: `CF-IPCountry` header, fallback to a local GeoLite2 database, fallback to the default rate row (`XX`).

**Recording:** one `views` row per finished visit (valid or invalid, with reason), written in the same database transaction as the dedup key and the pending earning. Amount = rate for (country, level) at that time, stored on the row, so later rate changes never rewrite history. Raw rows are purged after a configurable number of days (default 60) once aggregated.

**Aggregation:** a scheduled command folds views into `daily_stats` (per user and day: views, earnings, referral earnings, average CPM). The dashboard reads `daily_stats`, not raw views.

## 8. Earnings and ledger

- A valid view creates a pending earning on the `views` row (`earn_micro`, `status = pending`, `matures_at = now + review_days`).
- A scheduled job (`ReleaseMaturedEarnings`, daily) groups matured pending views by user and, inside `DB::transaction` with a locked user row, moves the sum to `users.balance_micro` and writes **one** `Transaction` row per user per run (`action = view_earnings`). Ledger rows are per batch, not per view, so the table stays small.
- Referral commission (default 12%, editable) is computed in the same job and credited to the referrer as a separate `Transaction` (`action = referral_commission`), paid on top of, not taken from, the referred member's earnings.
- Withdrawal request: locks the user, checks balance and minimum, moves the amount to `users.processing_micro`, writes a `Transaction` (`withdraw_request`). Admin approve writes `withdraw_paid`; reject returns the money with `withdraw_rejected`.
- Admin cancelling earnings of a banned link reverses pending rows (status `cancelled`) and, if already released, writes a negative adjustment `Transaction`.
- Dashboard "Available", "Processing" and "Total paid" always equal the sums of these rows.

## 9. Ads

- `ad_slots`: name, placement (`step_1`, `step_2`, `step_3`, `final`, `top`, `bottom`), device (`all`, `desktop`, `mobile`), network label, HTML/JS code, active flag, sort order.
- The step page renders the active slots for its placement. Ad code is output as stored (admin-trusted), inside a container that is separate from the "Continue" button so ads never look like the button.
- The countdown button is outside every ad container; the page never auto-redirects.
- Admin CRUD with a preview and an on/off switch. Slots are cached (database cache) and the cache is cleared on save.

## 10. Member area pages

All under `/member`, English slugs, auth middleware, own layout `layouts/member` with sidebar and a quick shorten form in the header.

| Page | Route | Contents |
|---|---|---|
| Overview | `/member/dashboard` | totals (views, publisher earnings, referral earnings, total income, average CPM), chart, date picker; data from `POST /member/dashboard/data` (JSON), announcements |
| Notifications | `/member/notifications` | list of system notices |
| Statistics | `/member/statistics` | month report: day, views, earnings, CPM, referral earnings; month picker |
| Top Links | `/member/top-links` | top 10 links in a month by views and earnings |
| Links | `/member/links`, `/member/links/hidden` | list with alias filter, stats link, hide and restore |
| Withdrawals | `/member/withdrawals` | balance cards, traffic source note, request form, history |
| Tools | `/member/tools/quick`, `mass-shrinker`, `full-page-script`, `api` | token-based quick link URL, up to 20 URLs per run, copy-paste script, API docs with token |
| Referrals | `/member/referrals` | referral link, referred members and earnings |
| Settings | `/member/settings/profile`, `password`, `email` | profile and payout method (bank transfer, PayPal, USDT BEP20), password change, email change |
| Support | `/member/support` | contact form |

The public API is `GET /api/v1/shorten?api={token}&url={url}` returning `{"status":"success","shortenedUrl":"..."}`, rate limited.

## 11. Admin area (minimal, my call)

Needed to operate the system: settings (all rule parameters), rates (country x level), ad slots, blocked networks (import and cron refresh), fraud flags (suspicious members and links), link ban with earnings cancel, withdrawal approve or reject, notifications. Anything beyond this (roles, reports, installer) is out of scope.

## 12. Data model (new tables)

`users` (extend: role, status, api_token, referral_code, referred_by, balance_micro, processing_micro, payout method fields), `links`, `visit_sessions`, `views`, `view_dedup`, `daily_stats`, `cpm_rates`, `withdrawals`, `referrals`, `ad_slots`, `fraud_flags`, `blocked_networks`, `notifications`, `settings`, `transactions`. Money columns are `unsignedBigInteger` micro-USD. Models get factories and seeders.

## 13. Delivery phases (each ends with passing tests)

1. **Accounts and member shell:** real auth, `layouts/member`, sidebar, settings pages (profile, password, email).
2. **Links:** `links` table, shorten form, links list and hide, mass shrinker, quick link, API and token, reserved-alias rules.
3. **View counting:** visit sessions, step tokens, checks, dedup, `views`, rates, aggregation, release job. The main phase.
4. **Ads and admin:** ad slots, settings, rates, blocked networks, fraud flags.
5. **Money pages:** overview, statistics, top links, withdrawals, referrals with real data, withdrawal approval in admin.
6. **Hardening:** rate limiting, notifications, support form, polish, browser checks at 360, 768 and 1200 px.

## 14. Testing

Pest feature tests per rule with a controllable clock: unique window, step too early, reused or tampered token, wrong IP, missing captcha, blocked network, country fallback, invalid-view reason stored. Ledger tests: balance equals the sum of `Transaction` rows, release job is idempotent, withdrawal lock, referral math. Each phase includes a browser check with `playwright-cli`.

## 15. Left to the builder

Chart library choice (light, jQuery friendly), exact fingerprint properties, user-agent denylist source, format of the blocked-network import file, table indexes, how to seed demo data, the exact visual design of step pages, and wording of all English copy.

## 16. My calls (please check)

- Micro-USD integer money, rate stored on each view.
- Review period default 3 days; minimum withdrawal default 5 USD (matches the landing page), referral 12% (matches the landing page and rates page).
- Ledger rows per release batch, not per view.
- Cloudflare Turnstile as the captcha.
- Database dedup table instead of Redis.
- jQuery instead of the Alpine.js named in the proposal, as requested.
- Minimal admin area included in this scope.

## 17. Decisions from the design review (my calls, please check)

1. **Levels and steps.** Level N has exactly N steps (level 1 = 1 step, level 3 = 3 steps), as published on the Publisher Rates page. Turnstile is on the last step only. The rate table is keyed by country and the same level.
2. **Rate seed data.** `cpm_rates` is seeded from the example rates already on the Publisher Rates page (Vietnam 4.50 / 6.00 / 7.50 and so on, USD per 1,000 views). The fallback row `XX` uses the lowest seeded rate of each level. All rows are editable in admin, and the public page reads the same table afterwards.
3. **IP changes mid-flow.** A different user-agent hash is a hard reject (the token is bound to it). A different IP only marks the view invalid with reason `ip_changed`; the visitor still reaches the destination. Mobile networks must not lock real visitors out.
4. **Destination and expired sessions.** The destination is always returned after `finish`, even for invalid views. An expired or unknown session restarts the flow at `GET /{alias}`. A blocked network still gets the normal flow and an invalid view.
5. **Balances.** `users.balance_micro` is the available balance only. Pending is the sum of `views` with `status = pending`. Processing is `users.processing_micro`. Total paid is the sum of `withdraw_paid` transactions. The "dashboard equals the ledger" rule applies to available, processing and paid; pending is derived from views and has no ledger row by design.
6. **Referral commission and cancellations.** Commission is paid on top of the referred member's earnings. If earnings are cancelled later (banned link), the matching commission is reversed with a negative `Transaction` for the referrer. Invalid views never earn commission.
7. **First admin.** Created by a seeder and by an artisan command `make:admin`. The admin area uses the same sign-in with a `role` check and its own `/admin` routes and layout.
8. **Existing data.** No real users exist yet, so the default `users` migration is edited in place (not a new alter migration) and the demo auth JS is replaced by real forms.
9. **Aliases.** Case-sensitive, 3 to 32 characters from `A-Za-z0-9_-`, generated aliases are 6 characters. Reserved: `member`, `admin`, `api`, `go`, `login`, `register`, `forgot-password`, `blog`, `payout-rates`, `privacy`, `terms`, `build`, `images`, `storage`, `favicon.ico`, `robots.txt`.

## 18. Correction after cross-checking real sites (supersedes sections 10 and 17.9)

The member URLs, public URLs, alias format, API format and page layouts follow the real reference sites (link4m member area, link1s public site), not the names first proposed here. The authoritative list, with the evidence for each item and the open questions, is the table "Đối chiếu với web thật" at the top of `docs/superpowers/plans/2026-11-13-member-dashboard-view-counting.md`. Examples: `/member/popular-link` (not `/member/top-links`), `/member/withdraws`, `/member/user/profile`, `/auth/signin`, `/pages/terms`, `GET /{alias}/info`, `POST /links/shorten`, referral link `/ref/{code}`; generated aliases are mixed-case alphanumeric. Reference HTML, CSS and screenshots are stored locally in `docs/references/link4m/` (git-ignored).
