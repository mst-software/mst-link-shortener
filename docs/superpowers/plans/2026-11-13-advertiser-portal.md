# Advertiser Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let advertisers self-register, create keyword campaigns, fund a USD wallet through SePay or PayPal, get charged per valid visit, and track every visit (counted or not) in a funnel view.

**Architecture:** A new `advertiser` role with its own routes (`routes/advertiser.php`), layout and controllers under `App\Http\Controllers\Advertiser`. The wallet reuses `users.balance_micro`, `transactions` and `WalletService`. Charging happens inside `ViewRecorder`'s existing DB transaction through a new `AdvertiserBilling` service; top-ups go through a `TopUpService` backed by a `top_ups` table.

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4, Blade + AdminLTE (member layout style), jQuery, Laravel `Http` client for SePay QR/PayPal REST. No new composer or npm packages.

**Spec:** `docs/superpowers/specs/2026-11-13-advertiser-portal-design.md` (also read `2026-11-13-keyword-code-flow-design.md` for the campaign, widget and code flow it builds on).

## Global Constraints

- Everything in the repo is English: code, comments, UI copy, error messages, commits.
- Follow `mst-laravel-conventions` and `git-workflow`: branch `feat/advertiser-portal` (branched from `docs/advertiser-portal-design`, mention this in the PR), commit format `type(scope): subject`, one line, lowercase, no body. Stage files explicitly.
- Do not add composer/npm dependencies. PayPal and SePay are called with `Illuminate\Support\Facades\Http`.
- Shared hosting: no Redis, no persistent workers. Money is USD in micro units (`1 USD = 1_000_000`). Use `WalletService` for every balance change.
- Advertisers never see `invalid_reason`, full IPs or fingerprints; only `Counted` / `Not counted`.
- Advertiser and member are separate accounts: an advertiser gets 403 on `member/*`, `links/shorten` and `{alias}/info`; a member gets 403 on `advertiser/*`.
- JavaScript on our pages uses jQuery. Run `vendor/bin/pint --dirty --format agent` and the affected Pest tests before every commit.
- Env keys (the user fills the values): `SEPAY_API_KEY`, `PAYPAL_CLIENT_ID`, `PAYPAL_SECRET`, `PAYPAL_MODE` (`sandbox`|`live`), exposed through `config/services.php` and listed in `.env.example`.

## Review Focus

1. A SePay webhook delivered twice, or concurrently, credits the wallet once.
2. A SePay payload with `transferType` `out`, an unknown reference code, or a wrong amount never credits the wallet (wrong amount/expired is stored as `mismatch`).
3. Two valid views recorded when the wallet can pay for only one: the balance never goes negative and the second view has `advertiser_cost_micro = 0`.
4. Advertiser A requesting advertiser B's campaign, top-up or visits gets 404.
5. The advertiser campaign page and visit table never contain an invalid reason label, IP or fingerprint.
6. Cancelling the same view twice refunds the advertiser once.

## File Structure

- Create `app/Services/Advertiser/AdvertiserBilling.php`: price lookup, charge, refund, affordability.
- Create `app/Services/Advertiser/TopUpService.php`: top-up creation and completion for SePay and PayPal.
- Create `app/Services/Advertiser/CampaignReport.php`: funnel, daily series and visit query for one campaign.
- Create `app/Http/Controllers/Advertiser/{DashboardController,CampaignController,WalletController,TopUpController,ProfileController}.php`, `app/Http/Controllers/Auth/AdvertiserRegisterController.php`, `app/Http/Controllers/Webhooks/SepayController.php`, `app/Http/Controllers/Admin/AdvertiserController.php`.
- Create `app/Http/Middleware/{EnsureUserIsAdvertiser,EnsureUserIsNotAdvertiser}.php`.
- Create `app/Models/TopUp.php` (+ factory), `routes/advertiser.php`, `routes/webhooks.php`, `resources/views/layouts/advertiser.blade.php`, `resources/views/advertiser/*`, `resources/views/admin/advertisers/*`.
- Modify `app/Enums/{UserRole,TransactionAction}.php`, `app/Models/{Campaign,View,User}.php`, `app/Services/Views/ViewRecorder.php`, `app/Services/LinkBanService.php`, `app/Console/Commands/PurgeOldViews.php`, `app/Services/Visits/CodeRedeemer.php`, `app/Services/Views/VisitFlow.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/Admin/CampaignController.php`, admin settings request/view, `bootstrap/app.php`, `config/linkcash.php`, `config/services.php`, `.env.example`, `routes/web.php`.

---

### Task 1: Advertiser role, registration, route guards, layout

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsAdvertiser.php`, `app/Http/Middleware/EnsureUserIsNotAdvertiser.php`, `app/Http/Controllers/Auth/AdvertiserRegisterController.php`, `app/Http/Controllers/Advertiser/DashboardController.php`, `routes/advertiser.php`, `resources/views/layouts/advertiser.blade.php`, `resources/views/auth/advertiser-register.blade.php`, `resources/views/advertiser/dashboard.blade.php` (stub), migration adding `users.terms_accepted_at` (nullable timestamp)
- Modify: `app/Enums/UserRole.php` (case `Advertiser = 'advertiser'`, label `Advertiser`), `app/Models/User.php` (`isAdvertiser(): bool`, cast + fillable `terms_accepted_at`), `bootstrap/app.php` (aliases `advertiser`, `not-advertiser`; load `routes/advertiser.php` before `routes/web.php`), `routes/member.php` and the `auth` group in `routes/web.php` (add `not-advertiser`), `app/Http/Controllers/Auth/LoginController.php`
- Test: `tests/Feature/Advertiser/AdvertiserAccessTest.php`

**Interfaces:**
- Produces: route names `advertiser.register` (GET/POST `advertiser/signup`), `advertiser.dashboard` (GET `advertiser`), middleware aliases `advertiser`, `not-advertiser`; `User::isAdvertiser()`; `UserFactory::advertiser()` state (role advertiser, `terms_accepted_at` now); layout `layouts.advertiser` with menu Overview, Campaigns, Wallet, Profile (later tasks add the links as routes appear; use `Route::has` guards or add links in their own tasks).
- Consumes: existing `RegisterRequest` rules (extend with the same `accept` rule), `EnsureUserIsActive`.

- [ ] **Step 1: Write failing tests** in `AdvertiserAccessTest.php`: `it('registers an advertiser with accepted terms')` (POST `advertiser.register` with username/email/password/accept → user role `advertiser`, `terms_accepted_at` not null, redirect to `advertiser.dashboard`); `it('rejects registration without accepting the terms')`; `it('redirects each role to its own dashboard after login')`; `it('blocks an advertiser from member routes')` (GET `member.dashboard` → 403); `it('blocks a member from advertiser routes')` (GET `advertiser.dashboard` → 403); `it('blocks an advertiser from link shortening')` (POST `links.shorten` → 403).
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Advertiser/AdvertiserAccessTest.php` → FAIL.
- [ ] **Step 3: Implement** the enum case, migration, user helpers and factory state; `AdvertiserRegisterController::show(): View` / `store(RegisterRequest $request): RedirectResponse` (creates the user with `role = Advertiser`, no referral handling, logs in, redirects); the two middleware (403 via `abort_unless`/`abort_if`); `LoginController::store` redirects intended-or-default by role (advertiser → `advertiser.dashboard`, admin → `admin.dashboard`, member → `member.dashboard`); layout copied in style from `layouts/member.blade.php` with an Advertiser sidebar and the wallet balance in the header. Register page copy states that gambling and 18+ content is forbidden.
- [ ] **Step 4: Run** the test file → PASS; also run `php artisan test --compact tests/Feature/Member tests/Feature/Auth` → PASS (no regression).
- [ ] **Step 5: Commit** `feat(advertiser): add advertiser role, registration and route guards`.

### Task 2: Schema, enums, settings defaults

**Files:**
- Create: migrations for `campaigns` (`user_id` nullable foreignId → users nullOnDelete, `review_status` string(16) default `approved` so existing admin campaigns keep running, `review_note` nullable string, `price_micro` nullable unsignedBigInteger), `views` (`campaign_id` nullable foreignId → campaigns nullOnDelete, `advertiser_cost_micro` unsignedBigInteger default 0, index `[campaign_id, created_at]`), `top_ups` (see below); `app/Enums/ReviewStatus.php`, `app/Enums/TopUpStatus.php`, `app/Enums/TopUpProvider.php`; `app/Models/TopUp.php`; `database/factories/TopUpFactory.php`
- Modify: `app/Enums/TransactionAction.php` (add `Topup = 'topup'`, `CampaignCharge = 'campaign_charge'`, `ChargeRefund = 'charge_refund'` with labels), `app/Models/Campaign.php` (fillable `user_id`, `review_status`, `review_note`, `price_micro`; casts; `owner(): BelongsTo`), `app/Models/View.php` (fillable/casts for the new columns, `campaign(): BelongsTo`), `database/factories/CampaignFactory.php` (`forAdvertiser()` and `pending()` states), `config/linkcash.php` (defaults below), `config/services.php`, `.env.example`
- Test: `tests/Feature/Advertiser/AdvertiserSchemaTest.php`

`top_ups` columns: `id`, `user_id` (foreign, cascade), `provider` (`sepay|paypal`), `reference` (string, unique together with `provider`), `status` (`pending|completed|failed|mismatch`, default `pending`), `amount_micro` (unsignedBigInteger, USD to credit), `original_amount` (unsignedBigInteger, VND or USD cents-free whole units: VND whole dong, USD micro), `original_currency` (string(3)), `exchange_rate` (unsignedInteger nullable, VND per USD), `expires_at` (nullable), `completed_at` (nullable), `raw` (json nullable), timestamps; index `[user_id, created_at]`.

New `linkcash.defaults`: `advertiser_default_price_micro` = 10_000, `advertiser_min_topup_micro` = 5_000_000, `vnd_per_usd` = 25_000, `sepay_bank_name` = '', `sepay_account_number` = '', `sepay_account_name` = ''.

**Interfaces:**
- Produces: `ReviewStatus::{Pending,Approved,Rejected}`, `TopUpStatus::{Pending,Completed,Failed,Mismatch}`, `TopUpProvider::{Sepay,Paypal}`, `TopUp` model with `user(): BelongsTo`, casts (enums, `raw` array, datetimes, `amount_micro` int), `Campaign::owner()`, `View::campaign()`.

- [ ] **Step 1: Write failing tests**: `it('stores review status price and owner on a campaign')`, `it('defaults old campaigns to approved')`, `it('stores campaign id and advertiser cost on a view')`, `it('keeps a unique provider and reference pair on top ups')` (second insert throws `UniqueConstraintViolationException`).
- [ ] **Step 2: Run** the file → FAIL.
- [ ] **Step 3: Implement** the migrations (follow the sibling migration style, `down()` included), enums with `label()`, models, factories, config and `.env.example` keys (`SEPAY_API_KEY=`, `PAYPAL_CLIENT_ID=`, `PAYPAL_SECRET=`, `PAYPAL_MODE=sandbox`; `config/services.php` keys `sepay.api_key`, `paypal.client_id`, `paypal.secret`, `paypal.mode`).
- [ ] **Step 4: Run** the file and `php artisan test --compact tests/Feature/CampaignSchemaTest.php tests/Feature/ViewSchemaTest.php` → PASS.
- [ ] **Step 5: Commit** `feat(advertiser): add advertiser schema and enums`.

### Task 3: Billing service wired into view recording, bans and purge

**Files:**
- Create: `app/Services/Advertiser/AdvertiserBilling.php`
- Modify: `app/Services/Views/ViewRecorder.php` (inside `recordLocked`, after the view is created), `app/Services/LinkBanService.php` (the `Cancelled` update), `app/Console/Commands/PurgeOldViews.php`
- Test: `tests/Feature/Advertiser/AdvertiserBillingTest.php`

**Interfaces:**
- Produces: `AdvertiserBilling::pricePerViewMicro(Campaign $campaign): int` (campaign `price_micro` or `Settings::int('advertiser_default_price_micro')`); `AdvertiserBilling::canAfford(Campaign $campaign): bool` (true for campaigns without owner; otherwise owner `balance_micro >= price`); `AdvertiserBilling::chargeView(View $view, Campaign $campaign): void` (no-op unless the campaign has an owner and the view is not `Invalid`; debits through `WalletService::debit(..., TransactionAction::CampaignCharge, 'view:'.$view->id, ...)`, sets `advertiser_cost_micro`; on `InsufficientBalanceException` leaves cost 0); `AdvertiserBilling::refundViews(iterable $views): void` (for each view with cost > 0 and no existing `ChargeRefund` transaction with reference `'view-refund:'.$id`, credits the owner with `ChargeRefund` and keeps `advertiser_cost_micro` as historical record).
- Consumes: `WalletService`, `Settings`, `Campaign::owner`.

- [ ] **Step 1: Write failing tests**: `it('charges the campaign owner for a valid keyword view')` (record a valid view through `ViewRecorder` for a keyword session of an advertiser campaign priced 10_000 with balance 1_000_000 → balance 990_000, view `campaign_id` set, `advertiser_cost_micro` 10_000, one `campaign_charge` transaction); `it('uses the default price when the campaign has none')`; `it('does not charge an invalid view but still stores the campaign id')`; `it('does not charge admin campaigns without an owner')`; `it('records a free counted view and never goes negative when the wallet cannot pay')` (balance 5_000 → balance stays 5_000, cost 0, view still `Pending` status for the publisher); `it('refunds once when pending views of a banned link are cancelled')` (ban twice and call `refundViews` twice → one `charge_refund`); `it('keeps views that belong to an advertiser campaign when purging')` (old Invalid view with `campaign_id` survives `views:purge`, one without is deleted).
- [ ] **Step 2: Run** the file → FAIL.
- [ ] **Step 3: Implement** the service; in `ViewRecorder::recordLocked` write `campaign_id` from `$locked->campaign_id` when `mode` is keyword (also for invalid views) and call `chargeView` for valid ones inside the same transaction; in `LinkBanService::ban` call `refundViews($pending)` right after the cancel update (inside its transaction); in `PurgeOldViews` add `->whereNull('campaign_id')` to the view delete query.
- [ ] **Step 4: Run** the new file and `tests/Feature/ViewRecordingTest.php tests/Feature/Admin/NetworksFraudBansTest.php tests/Feature/KeywordVisitFlowTest.php` → PASS.
- [ ] **Step 5: Commit** `feat(advertiser): charge campaign owners per valid view`.

### Task 4: Campaign availability gating

**Files:**
- Modify: `app/Models/Campaign.php` (`scopeAvailable`), `app/Services/Visits/CodeRedeemer.php`, `app/Services/Views/VisitFlow.php` (only if it does not already go through `scopeAvailable`/`available()`; read lines ~289-345 first)
- Test: `tests/Feature/Advertiser/CampaignAvailabilityTest.php`

**Interfaces:**
- Consumes: `AdvertiserBilling`, `ReviewStatus`, `UserStatus`.
- Produces: `scopeAvailable` additionally requires, for campaigns with `user_id`: `review_status = approved`, owner `status = active`, owner `balance_micro >=` the campaign's price (campaign `price_micro` or the default setting; implement in SQL with `COALESCE`). Campaigns without owner behave as before.

- [ ] **Step 1: Write failing tests**: `it('hides pending and rejected advertiser campaigns')`, `it('hides campaigns whose owner is locked')`, `it('hides campaigns whose owner cannot pay one view')`, `it('keeps admin campaigns available')`, `it('rejects an unused issued code once the advertiser campaign is no longer available')` (flip campaign to `pending` after the code is issued → redeem fails and the session continues in wait mode per spec 12.6), `it('shows the campaign again after the owner tops up')`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** the scope; make `CodeRedeemer` refuse codes whose campaign is not `available()` (reuse the existing wait-mode fallback result type; do not invent a new one).
- [ ] **Step 4: Run** the new file plus `tests/Feature/CodeRedeemerTest.php tests/Feature/WidgetApiTest.php tests/Feature/VisitFlowTest.php` → PASS.
- [ ] **Step 5: Commit** `feat(advertiser): gate campaigns on review, owner status and balance`.

### Task 5: Advertiser campaign management and admin review

**Files:**
- Create: `app/Http/Controllers/Advertiser/CampaignController.php`, `app/Http/Requests/Advertiser/StoreCampaignRequest.php` (and `UpdateCampaignRequest` if rules differ), `resources/views/advertiser/campaigns/{index,form}.blade.php`, `app/Http/Requests/Admin/ReviewCampaignRequest.php`
- Modify: `routes/advertiser.php`, `app/Http/Controllers/Admin/CampaignController.php` (+ `routes/admin-campaigns.php`: `PATCH campaigns/{campaign}/review` named `admin.campaigns.review`), `resources/views/admin/campaigns/index.blade.php` (owner column, review status, "Pending review" filter, approve/reject with note)
- Test: `tests/Feature/Advertiser/CampaignManagementTest.php`, extend `tests/Feature/Admin/CampaignAdminTest.php`

**Interfaces:**
- Produces: routes `advertiser.campaigns.{index,create,store,edit,update}` and `advertiser.campaigns.toggle` (PATCH), policy-free ownership via `abort_unless($campaign->user_id === $request->user()->id, 404)`; admin route `admin.campaigns.review` taking `review_status` (`approved|rejected`) and `review_note` (required when rejected).
- Consumes: Task 2 columns; reuse the admin `StoreCampaignRequest` helpers for domain/keyword/widget-key/display-domain generation (extract a shared method rather than copying).

- [ ] **Step 1: Write failing tests**: `it('creates a pending campaign owned by the advertiser')` (fields: name, keyword, domain, daily_limit, optional dates, three optional images; defaults for weight/stage seconds, generated `widget_key` and masked `display_domain`); `it('lists only my campaigns')`; `it('returns 404 for another advertiser campaign on edit, update and toggle')`; `it('sends an approved campaign back to pending when keyword or domain change')`; `it('keeps approval when only name or daily limit change')`; `it('resubmits a rejected campaign as pending and clears the note')`; `it('lets the advertiser pause and resume only approved campaigns')`; admin: `it('approves a pending campaign')`, `it('requires a note when rejecting')`, `it('filters campaigns pending review')`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** controllers, requests, views (English copy; show the widget `<script>` snippet and `review_note` only when approved/rejected respectively), admin review action and list changes.
- [ ] **Step 4: Run** both test files and `tests/Feature/Admin` → PASS.
- [ ] **Step 5: Commit** `feat(advertiser): add campaign management and admin review`.

### Task 6: SePay top-up

**Files:**
- Create: `app/Services/Advertiser/TopUpService.php`, `app/Http/Controllers/Webhooks/SepayController.php`, `routes/webhooks.php`, `app/Http/Requests/Advertiser/StoreSepayTopUpRequest.php`, `app/Http/Controllers/Advertiser/TopUpController.php` (SePay part)
- Modify: `bootstrap/app.php` (load `routes/webhooks.php`; add `webhooks/*` to the CSRF exceptions), `routes/advertiser.php`
- Test: `tests/Feature/Advertiser/SepayTopUpTest.php`

**Interfaces:**
- Produces on `TopUpService`: `createSepay(User $user, int $vnd): TopUp` (rejects below `advertiser_min_topup_micro` converted at the current rate; stores `exchange_rate`, `amount_micro = intdiv($vnd * 1_000_000, $rate)`, `reference = 'LC'.$user->id.strtoupper(Str::random(6))`, `expires_at = now()+24h`, status pending); `completeFromSepay(array $payload): TopUpStatus|null` (see rules below); `complete(TopUp $topUp, array $raw = []): void` (locks the row, no-op unless status pending, credits via `WalletService::credit(..., TransactionAction::Topup, 'topup:'.$id)`, sets completed, in one DB transaction). Route `advertiser.topups.sepay` (POST creating the order and redirecting to the wallet page with the QR data), `POST webhooks/sepay` named `webhooks.sepay`.
- QR URL helper `TopUp::sepayQrUrl(): string` building `https://qr.sepay.vn/img?acc={account}&bank={bank}&amount={vnd}&des={reference}` from the settings `sepay_account_number` / `sepay_bank_name`.
- Webhook rules: header `Authorization: Apikey {config('services.sepay.api_key')}` must match (compare with `hash_equals`), otherwise 401. Payload fields used: `transferType` (`in` only), `transferAmount`, `content`, `id` (stored in `raw`). Find the pending top-up whose reference appears in `content`. Exact amount match and not expired → `complete`. Known reference but wrong amount, expired, or already non-pending → mark `mismatch` (only when still pending) without crediting. Unknown reference or `out` → ignore. Always respond `200 {"success": true}` after authentication so SePay stops retrying.

- [ ] **Step 1: Write failing tests**: `it('creates a pending top up with a locked rate and a unique reference')` (change the rate setting afterwards and confirm `amount_micro` is unchanged); `it('refuses amounts below the minimum')`; `it('credits the wallet once when the webhook arrives twice')`; `it('rejects webhooks without the api key')` (401, no change); `it('ignores outgoing transfers')`; `it('marks a wrong amount as mismatch without crediting')`; `it('marks an expired top up as mismatch without crediting')`; `it('ignores unknown references')`; `it('writes a topup transaction with before and after balances')`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** service, controller, routes, request. Use `Cache::lock` is not needed; rely on the row lock in `complete`.
- [ ] **Step 4: Run** the file → PASS.
- [ ] **Step 5: Commit** `feat(advertiser): add sepay top up and webhook`.

### Task 7: PayPal top-up

**Files:**
- Create: `app/Http/Requests/Advertiser/StorePaypalTopUpRequest.php`
- Modify: `app/Services/Advertiser/TopUpService.php`, `app/Http/Controllers/Advertiser/TopUpController.php`, `routes/advertiser.php`
- Test: `tests/Feature/Advertiser/PaypalTopUpTest.php`

**Interfaces:**
- Produces on `TopUpService`: `createPaypal(User $user, int $usdMicro): array{top_up: TopUp, approve_url: string}` (min amount from `advertiser_min_topup_micro`; POST `/v1/oauth2/token` with basic auth, then POST `/v2/checkout/orders` with intent `CAPTURE`, one purchase unit in `USD` with the amount as a 2-decimal string, return/cancel URLs from the routes below; stores the PayPal order id as `reference`, `original_currency = 'USD'`); `capturePaypal(TopUp $topUp): TopUpStatus` (POST `/v2/checkout/orders/{id}/capture`; calls `complete()` only when the response status is `COMPLETED` and the captured amount equals `amount_micro`, otherwise `failed`). Base URL `https://api-m.sandbox.paypal.com` or `https://api-m.paypal.com` by `config('services.paypal.mode')`. Routes: `advertiser.topups.paypal` (POST), `advertiser.topups.paypal.return` (GET with `token` = order id; the top-up must belong to the current user), `advertiser.topups.paypal.cancel` (GET, marks failed).

- [ ] **Step 1: Write failing tests** with `Http::fake()` for the token, order and capture endpoints: `it('creates a PayPal order and redirects to the approval url')`; `it('credits the wallet when the capture is completed')`; `it('does not credit when the capture is not completed')`; `it('does not credit twice when the return url is opened twice')`; `it('does not let another user capture my order')` (404); `it('refuses amounts below the minimum')`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** with the `Http` client (timeouts and `throw()` handled into a flash error, never a 500).
- [ ] **Step 4: Run** the file and `tests/Feature/Advertiser/SepayTopUpTest.php` → PASS.
- [ ] **Step 5: Commit** `feat(advertiser): add paypal top up`.

### Task 8: Wallet page

**Files:**
- Create: `app/Http/Controllers/Advertiser/WalletController.php`, `resources/views/advertiser/wallet.blade.php`
- Modify: `routes/advertiser.php`, layout menu
- Test: `tests/Feature/Advertiser/WalletPageTest.php`

**Interfaces:** Consumes `TopUpService`, `TopUp::sepayQrUrl()`. Route `advertiser.wallet` (GET). Shows balance, minimum top-up, two forms (SePay in VND with the live USD preview from the current rate, PayPal in USD), the QR and transfer details of the latest pending SePay top-up, paginated top-up history (provider, amount, status) and paginated transactions (action label, amount, balance after).

- [ ] **Step 1: Write failing tests**: `it('shows balance, pending sepay qr details and history')`; `it('shows only my top ups and transactions')`; `it('shows the minimum top up and the current rate')`.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement** controller and view (a small jQuery snippet updates the USD preview). **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** `feat(advertiser): add wallet page`.

### Task 9: Overview, campaign tracking page, profile

**Files:**
- Create: `app/Services/Advertiser/CampaignReport.php`, `resources/views/advertiser/campaigns/show.blade.php`, `app/Http/Controllers/Advertiser/ProfileController.php`, `resources/views/advertiser/profile.blade.php`
- Modify: `app/Http/Controllers/Advertiser/DashboardController.php` and its view, `app/Http/Controllers/Advertiser/CampaignController.php` (`show`), `routes/advertiser.php`
- Test: `tests/Feature/Advertiser/CampaignTrackingTest.php`, `tests/Feature/Advertiser/OverviewTest.php`

**Interfaces:**
- Produces on `CampaignReport` (constructed with a `Campaign`): `funnel(CarbonInterface $from, CarbonInterface $to): array{started:int, issued:int, counted:int, not_counted:int, spent_micro:int}` (`started` = `code_attempts` created in range; `issued` = those with `issued_at`; `counted`/`not_counted` = `views` with this `campaign_id`, status not `Invalid` / `Invalid`; `spent_micro` = sum of `advertiser_cost_micro` of this campaign's views that are not `Cancelled`); `daily(CarbonInterface $from, CarbonInterface $to): list<array{date:string, started:int, issued:int, counted:int}>`; `visits(?string $status): Builder<View>` (status `counted|not_counted|null`, selecting only `created_at`, `country`, `status`, `advertiser_cost_micro`).
- Routes: `advertiser.campaigns.show` (GET, 404 for other owners, date range and status filter via query string, paginate 25), `advertiser.profile` (+ password/email update posting to existing member-style requests `ChangePasswordRequest`/`ChangeEmailRequest`).
- Page layout is the approved "B" mockup: header with status badge and Pause/Resume, four-step funnel (Started, Code issued, Used - Counted, Used - Not counted), cards Spent / Wallet left / Today vs daily limit, a daily chart (reuse `resources/js/chart.js` as the member dashboard does), visits table with All/Counted/Not counted filter, the widget snippet when approved, a "Paused: insufficient balance" notice when active but `canAfford` is false. Overview shows balance, spend today and last 7 days, campaign counts by review status, links to Top up / New campaign, low-balance warning (balance below 10× default price).

- [ ] **Step 1: Write failing tests**: `it('counts the funnel steps for a date range')` (3 attempts, 2 issued, 1 counted, 1 invalid view → started 3, issued 2, counted 1, not_counted 1); `it('shows not counted visits to the advertiser as Not counted only')`; `it('never renders an invalid reason, full ip or fingerprint')` (create an invalid view with reason `bot`, ip `203.0.113.7`; assert response does not contain `Bot`, `bot`-reason label, `203.0.113.7`); `it('filters visits by status')`; `it('returns 404 for another advertiser campaign')`; `it('shows the insufficient balance notice')`; `it('shows overview totals for today and seven days')`.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement** report, controllers, views. **Step 4: Run** both files → PASS.
- [ ] **Step 5: Commit** `feat(advertiser): add campaign tracking, overview and profile`.

### Task 10: Admin advertisers and settings

**Files:**
- Create: `app/Http/Controllers/Admin/AdvertiserController.php`, `resources/views/admin/advertisers/{index,show}.blade.php`
- Modify: `routes/admin-campaigns.php` (or a new `routes/admin-advertisers.php` required from `routes/admin.php`), admin layout menu, `app/Http/Requests/Admin/UpdateSettingsRequest.php`, the settings controller and `resources/views/admin/settings/edit.blade.php`, `app/Http/Controllers/Admin/CampaignController.php` form (per-campaign price field `price_usd`, nullable)
- Test: `tests/Feature/Admin/AdvertiserAdminTest.php`, extend `tests/Feature/Admin/SettingsAndRatesTest.php`

**Interfaces:**
- Settings added to the form and request (store money as micro like `min_withdraw_usd` does): `advertiser_default_price_usd` (> 0), `advertiser_min_topup_usd`, `vnd_per_usd` (integer ≥ 1), `sepay_bank_name`, `sepay_account_number`, `sepay_account_name` (nullable strings).
- Warning (non-blocking, flashed after save and shown beside the field) when the default price or a campaign's price is lower than `max(cpm_rates.rate)` per view: read `CpmRate` to compute the highest per-view payout; if the column layout makes this ambiguous, read `RateResolver::ratePerViewMicro` and iterate the table's country/level rows.
- Routes `admin.advertisers.index|show` and `admin.advertisers.toggle` (lock/unlock via `UserStatus`); `show` lists the advertiser's campaigns, top-ups (including `mismatch` rows with a "Credit manually" action that calls `TopUpService::complete` for `mismatch` rows after an explicit confirm) and transactions, and the campaign visit rows with the full invalid reason (admin only).

- [ ] **Step 1: Write failing tests**: `it('lists advertisers with balance, total topped up and total spent')`; `it('locks an advertiser and their campaigns stop being available')`; `it('credits a mismatched top up once from the admin screen')`; `it('forbids non-admins')`; `it('saves the advertiser settings')`; `it('warns when the price is below the highest publisher rate')`; `it('stores a per campaign price')`.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** both files and `php artisan test --compact` (full suite) → PASS.
- [ ] **Step 5: Commit** `feat(admin): manage advertisers, prices and top up settings`.

### Task 11: Browser verification and wrap-up

**Files:** none new.

- [ ] **Step 1:** Run the full suite and `vendor/bin/pint --format agent`; commit any formatting as `style(advertiser): format advertiser code`.
- [ ] **Step 2:** With the `qa-reviewer` agent (playwright-cli), check at desktop and 375 px width: advertiser signup, create campaign, admin approval, wallet page (SePay QR visible, PayPal button), campaign tracking page (funnel, filter, no reason text), member area returns 403 for the advertiser. Fix findings.
- [ ] **Step 3:** Open the PR to `main` per `git-workflow` (title `feat(advertiser): add advertiser portal`, English bullets including what could not be verified: live SePay/PayPal calls need real keys in `.env`).

---

## Self-review notes

- Spec coverage: 6.1 → T1; 6.2 → T2, T4, T5; 6.3 → T6, T7, T8; 6.4 → T3; 6.5 → T8, T9; 6.6 → T5, T10; section 12 (purge, refund on every cancel path, free counted view, derived pause, rate lock, 24h expiry, mismatch screen, terms checkbox, locked advertiser rejects unused codes) → T3, T4, T6, T10, T1; section 13 price warning → T10.
- Refunds run only for cancelled views; `LinkBanService` is the only code path that sets `Cancelled`. Any later path must call `AdvertiserBilling::refundViews`.
- Names used across tasks: `AdvertiserBilling::{pricePerViewMicro,canAfford,chargeView,refundViews}`, `TopUpService::{createSepay,completeFromSepay,complete,createPaypal,capturePaypal}`, `CampaignReport::{funnel,daily,visits}`, route names listed in each Interfaces block.
