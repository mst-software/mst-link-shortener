# LinkCash

LinkCash is a link shortener where members earn money from valid visits to their short links. Visitors pass a few
ad steps and a captcha before they reach the destination; each valid view is paid at a CPM rate that depends on the
visitor's country and the link level. Advertisers top up a wallet (PayPal or bank transfer through SePay) and run
campaigns, including a keyword code widget they embed on their own site. Admins manage members, links, payouts,
fraud flags and every operational setting from the admin area.

Built with Laravel 12, PHP 8.4, Blade, Tailwind CSS 4 and Vite. Tests use Pest.

## Requirements

- PHP 8.2 or newer (8.4 recommended) with the extensions `pdo_mysql` (or `pdo_sqlite` for local use), `mbstring`, `openssl`, `tokenizer`,
  `xml`, `ctype`, `fileinfo`, `bcmath`, `curl`, `intl` and `gd` built with WebP support (check with
  `php -r "var_dump(gd_info()['WebP Support'] ?? false);"`)
- Composer 2
- Node.js 20 or newer with npm, on your own machine only (to build the frontend assets)
- MySQL 8 or MariaDB 10.6+ in production (SQLite works for local development)

Production targets cPanel shared hosting: PHP, MySQL and cron only. The app needs no queue worker, Redis, Node.js or
other long-running process on the server.

## Local setup

```bash
composer run setup        # composer install, copy .env, key:generate, migrate, npm install, npm run build
php artisan db:seed       # default settings, CPM rates, example ad slot and campaign, a local admin and test user
composer run dev          # php artisan serve
npm run dev               # Vite with hot reload, in a second terminal
```

`composer run setup` uses SQLite (`database/database.sqlite`, created on the first migration) by default. To use MySQL, set the `DB_*` values in
`.env` before migrating. The local seeder creates the admin `admin` / `admin@example.com` (only when
`APP_ENV=local`); its password is the factory default `password`.

## Running tests

```bash
php artisan test --compact                       # the whole suite (in-memory SQLite, no setup needed)
php artisan test --compact --filter=Preflight    # a subset
vendor/bin/pint --dirty                          # format changed PHP files
```

Tests never touch the database in `.env`: `phpunit.xml` switches to an in-memory SQLite database.

## Production deployment (cPanel)

The steps below assume a cPanel account with the username `USER` and the app in `/home/USER/linkcash`. Commands that
start with `php artisan` run in cPanel **Terminal** when your host offers it; otherwise add each one as a one-off cron
job (see step 6) and remove it after it ran.

### 1. PHP version and extensions

In cPanel **MultiPHP Manager** (or **Select PHP Version** on CloudLinux) choose PHP 8.2 or newer (8.4 recommended)
for the domain, and enable the extensions `gd`, `intl`, `mbstring`, `bcmath`, `pdo_mysql`, `fileinfo`, `curl`,
`openssl`, `tokenizer`, `xml` and `ctype`. `gd` needs WebP support; check it with
`php -r "var_dump(gd_info()['WebP Support'] ?? false);"`.

### 2. Database and mail

- In **MySQL Databases** create a database and a user, add the user to the database with **All Privileges**, and put
  the names (cPanel prefixes them with `USER_`) in the `DB_*` values of `.env` with `DB_HOST=localhost`.
- In **Email Accounts** create a mailbox for the site (for example `no-reply@your-domain`). Enter its SMTP server on the
  admin Email settings page after the first sign-in (host `mail.your-domain`, port 465 with SSL or 587 with TLS).

### 3. Build and upload the code

There is no Node.js on the server, and `public/build` is not in git (`.gitignore`), so build the assets on your own
machine and upload them with the code:

```bash
composer install --no-dev --optimize-autoloader   # or run it in cPanel Terminal after the upload
npm ci && npm run build                           # writes public/build
```

Upload the project (including `vendor/` when you installed it locally, and `public/build/`) to `/home/USER/linkcash`
with the cPanel **File Manager** (upload a zip and extract it) or FTP. Do not upload `.env`, `node_modules/` or
`database/database.sqlite`. Make `storage/` and `bootstrap/cache/` writable by your account (0755 for folders is
usually enough on cPanel, where PHP runs as your user).

### 4. Document root

Only `public/` may be reachable from the web. Pick one:

- **Point the domain at `public/` (preferred).** In **Domains**, set the document root of the domain to
  `linkcash/public`. Nothing else changes.
- **Symlink `public_html`.** When the document root of the main domain cannot be changed, move the old `public_html`
  aside and replace it with a symlink: `ln -s /home/USER/linkcash/public /home/USER/public_html`.
- **Copy `public/` into `public_html`.** When symlinks are not possible, copy the contents of `public/` (including
  `.htaccess` and `build/`) into `public_html`, then edit `public_html/index.php`: replace each `__DIR__.'/../` with
  `__DIR__.'/../linkcash/` and add `$app->usePublicPath(__DIR__);` after the `$app = require_once ...` line, so Laravel
  looks for `build/` and `storage/` in `public_html`. Copy `public/build` again on every deploy.

`public/.htaccess` already sends every request that is not a file to `index.php`. Serve the site over HTTPS only
(**SSL/TLS Status** > AutoSSL).

### 5. Configure

```bash
cp .env.example .env      # then fill in the PRODUCTION block at the end of the file
php artisan key:generate  # only once; keep the key, it encrypts the secrets saved in the admin settings
php artisan migrate --force
php artisan db:seed --class=CpmRateSeeder --force   # optional starting CPM rates, editable in the admin
php artisan storage:link
php artisan optimize
```

- In `.env` set at least `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, the `DB_*` values,
  `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database` and `SESSION_DRIVER=database` (or `file` for both), and
  `QUEUE_CONNECTION=sync`. The commented PRODUCTION block at the end of `.env.example` lists them. Do not use Redis or
  a queue connection that needs a worker: nothing runs one on shared hosting.
- `php artisan storage:link` creates the `public/storage` symlink that serves images uploaded to this server. If your
  host does not allow symlinks from PHP, create it in **File Manager** or Terminal
  (`ln -s /home/USER/linkcash/storage/app/public /home/USER/linkcash/public/storage`; in the copy layout point
  `public_html/storage` there instead), or save uploads on S3-compatible storage (see step 8) so no link is needed.
- On every deploy: upload the new code and `public/build`, then run `php artisan migrate --force` and
  `php artisan optimize`. Use `php artisan down` / `php artisan up` around deploys that change the database.
- API tokens are stored encrypted with a SHA-256 hash for the lookup (migration
  `hash_and_encrypt_user_api_tokens`). On the deploy that ships it, run `php artisan down`, switch the code, run
  `php artisan migrate --force`, then `php artisan up`, so nobody signs up on the old code with a plain token in
  between. If sign-ups did happen during the switch, run the migration's `up()` once more; it only converts rows that
  still hold a plain token and leaves converted rows alone:
  `php artisan tinker --execute '(require database_path("migrations/2026_11_16_030934_hash_and_encrypt_user_api_tokens.php"))->up();'`.
  Tokens it cannot decrypt (encrypted with another `APP_KEY`) are skipped and logged; those members see a notice and
  can generate a new token on the Developers API page. Keep `APP_KEY` stable: changing it makes every stored API
  token unreadable.

Emails and notifications are sent during the request, so there is no queue worker to start or restart.

### 6. Scheduler (cron)

In cPanel **Cron Jobs** add one job that runs every minute (common setting "Once Per Minute", `* * * * *`):

```cron
* * * * * /usr/local/bin/php /home/USER/linkcash/artisan schedule:run >> /dev/null 2>&1
```

Use the PHP binary of the version you selected in step 1 if the default differs, for example
`/opt/cpanel/ea-php84/root/usr/bin/php` (MultiPHP) or `/opt/alt/php84/usr/bin/php` (CloudLinux).

It runs view aggregation (every 5 minutes), SePay sync (every minute), fraud scan (hourly), earnings release, old view
purge and sign-in history purge (daily). `php artisan schedule:health` and the admin dashboard show when each job last
succeeded.

Sign-in history: every sign-up and sign-in stores the IP address, a SHA-256 hash of the browser user agent, the user
agent trimmed to 255 characters and the country (from `CF-IPCountry`, when trusted) in `user_logins`. Nothing else is
stored and nothing is sent to third parties. Admins see related accounts on the user page and
`multi_account_referral` fraud flags; nobody is banned automatically. `logins:purge` deletes rows older than the
"Sign-in history retention" setting (Admin > Settings > Traffic and earnings, default 90 days).

### 7. Cloudflare and reverse proxies

The visitor IP decides view deduplication, the IP change rule, blocked networks, rate limits and audit logs, and the
country decides the CPM rate. Tell the app which proxies to trust with `TRUSTED_PROXIES`
(`config/trustedproxy.php`):

| Setup | `.env` |
| --- | --- |
| Visitors connect straight to the server | `TRUSTED_PROXIES=` (empty, the default) |
| Behind Cloudflare | `TRUSTED_PROXIES=cloudflare` |
| Behind a load balancer or proxy on 10.0.0.0/8 (no Cloudflare) | `TRUSTED_PROXIES=10.0.0.0/8` |
| Behind a single proxy that is the only way in | `TRUSTED_PROXIES=*` (preflight warns; prefer listing its IPs) |
| Cloudflare in front of another proxy or load balancer | **Not supported** (see below) |

Cloudflare in front of another proxy (for example `TRUSTED_PROXIES=cloudflare,10.0.0.0/8`) is deliberately not
supported for the visitor IP and country: PHP then sees the inner proxy, not a Cloudflare edge, so `CF-Connecting-IP`
and `CF-IPCountry` are ignored, the visitor IP comes from `X-Forwarded-For` only and every visitor gets the country
`XX`. `php artisan app:preflight` warns about this combination. Let Cloudflare connect to the web server that runs
PHP directly instead.

- With `cloudflare`, requests that really come from a Cloudflare edge take the visitor IP from `CF-Connecting-IP`;
  anyone connecting directly cannot fake it with `X-Forwarded-For`.
- The visitor country is read from `CF-IPCountry` only on requests from a Cloudflare edge, and only while
  `TRUST_COUNTRY_HEADER` is on (by default it is on exactly when `TRUSTED_PROXIES` contains `cloudflare`). Otherwise
  every visitor gets the country `XX` and its rate. The edge must connect to PHP directly; a proxy in between hides
  the edge address, so the country header is ignored.
- The Cloudflare ranges are bundled in `config/trustedproxy.php`. Compare them with
  <https://www.cloudflare.com/ips/> before going live and whenever Cloudflare announces changes.
- In Cloudflare use SSL mode "Full (strict)".

### 8. File storage (S3-compatible)

Uploaded images (logos, favicon, campaign images) are saved in `storage/app/public` on the hosting account by default.
To keep them off the hosting account, use S3-compatible storage such as Cloudfly: create a bucket that allows public
reads and an access key, then fill in **Admin > Settings > File storage** (access key, secret, region, bucket, the
provider's S3 API address as the endpoint, and optionally a public CDN address). Turn on path-style addresses when the
provider asks for them, and use **Test connection** to check the settings. The same values can be set in `.env`
(`AWS_*`); the admin page overrides them once saved.

### 9. Backups

The app has no backup command. Use the cPanel **Backup** (or **JetBackup**) feature of your host, or export the
database from **phpMyAdmin**, and keep copies off the server. Images saved on this server live in `storage/app/public`
and are part of the home directory backup.

### 10. Preflight

Run `php artisan app:preflight` on the server after configuring it. It prints PASS, WARN or FAIL for debug mode, the
environment, the app key, an https `APP_URL`, the secure session cookie, trusted proxies, the database connection,
pending migrations, the storage link, the frontend build, the queue connection (it should be `sync`), the scheduler
heartbeat, the mailer, captcha keys, PayPal, SePay and the support email. It exits with a non-zero code when any check
fails.

### 11. Before going live

- Have a person review the Terms of Use and Privacy Policy (`resources/views/terms.blade.php`,
  `resources/views/privacy.blade.php`) for your business and jurisdiction.
- Run `php artisan app:preflight` and fix every FAIL.

## First-time admin setup

1. Create an administrator: `php artisan make:admin <username> <email>` (it asks for the password).
2. Sign in at `/auth/signin` and turn on two-factor authentication at `/member/user/two-factor`.
3. Open **Admin > Settings** and fill in:
   - **General**: site name, support email, social links, open or closed registration and maintenance mode.
   - **Email**: the SMTP server and sender (the cPanel mailbox from deployment step 2). Use "Send test email" to check
     it.
   - **Payments**: minimum withdrawal and top-up, exchange rate, payout methods members may choose, PayPal client ID,
     secret and mode (`live` to collect real money), and the SePay API token and bank account.
   - **Traffic**: view counting rules (seconds per step, unique visitor window, review days, visit mode, tutorial
     video) and the default advertiser price.
   - **Captcha**: the provider (Cloudflare Turnstile, hCaptcha or Google reCAPTCHA) and its site and secret keys.
   - **File storage**: this server or S3-compatible storage such as Cloudfly (deployment step 8).
4. Check **Admin > Rates** for the CPM rate of each country and link level.

Secrets saved on these pages are encrypted with `APP_KEY` and override the matching `.env` values.
