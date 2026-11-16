# LinkCash

LinkCash is a link shortener where members earn money from valid visits to their short links. Visitors pass a few
ad steps and a captcha before they reach the destination; each valid view is paid at a CPM rate that depends on the
visitor's country and the link level. Advertisers top up a wallet (PayPal or bank transfer through SePay) and run
campaigns, including a keyword code widget they embed on their own site. Admins manage members, links, payouts,
fraud flags and every operational setting from the admin area.

Built with Laravel 12, PHP 8.4, Blade, Tailwind CSS 4 and Vite. Tests use Pest.

## Requirements

- PHP 8.4 with the extensions `pdo_mysql` (or `pdo_sqlite` for local use), `mbstring`, `openssl`, `tokenizer`,
  `xml`, `ctype`, `fileinfo`, `bcmath`, `curl`, `intl` and `gd` built with WebP support (check with
  `php -r "var_dump(gd_info()['WebP Support'] ?? false);"`)
- Composer 2
- Node.js 20 or newer with npm (only to build the frontend assets)
- MySQL 8 or MariaDB 10.6+ in production (SQLite works for local development)
- The MySQL or MariaDB client tools (`mysqldump`) on the server for backups

## Local setup

```bash
composer run setup        # composer install, copy .env, key:generate, migrate, npm install, npm run build
php artisan db:seed       # default settings, CPM rates, example ad slot and campaign, a local admin and test user
composer run dev          # server, queue listener, log tail and Vite together
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

## Production deployment

### 1. Server and code

- Point the web server's document root at `public/`, never the project root. Send every request that is not a file
  to `public/index.php`.
- Serve the site over HTTPS only.
- Install and build:

  ```bash
  composer install --no-dev --optimize-autoloader
  npm ci && npm run build
  cp .env.example .env      # then fill in the PRODUCTION block at the end of the file
  php artisan key:generate  # only once; keep the key, it encrypts the secrets saved in the admin settings
  php artisan migrate --force
  php artisan db:seed --class=CpmRateSeeder --force   # optional starting CPM rates, editable in the admin
  php artisan storage:link
  php artisan optimize
  ```

- In `.env` set at least `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, the `DB_*` values,
  `SESSION_SECURE_COOKIE=true` and `QUEUE_CONNECTION=database` (or `redis`). The commented PRODUCTION block at the end
  of `.env.example` lists them.
- On every deploy: `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`,
  `php artisan migrate --force`, `php artisan optimize`, `php artisan queue:restart`.

### 2. Queue worker

The app sends its emails during the request today, but use `QUEUE_CONNECTION=database` (or `redis`) and keep a
worker running so any queued work is processed. With Supervisor:

```ini
[program:linkcash-worker]
command=php /var/www/linkcash/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/linkcash/storage/logs/worker.log
```

Workers keep the configuration they started with. Run `php artisan queue:restart` after each deploy **and after
saving the Email or Payments settings pages**, otherwise queued work keeps using the old values (the admin pages show
the same reminder).

### 3. Scheduler

Add one cron entry for the web server user:

```cron
* * * * * cd /var/www/linkcash && php artisan schedule:run >> /dev/null 2>&1
```

It runs view aggregation (every 5 minutes), SePay sync (every minute), fraud scan (hourly), earnings release,
old view purge and the database backup (daily). `php artisan schedule:health` and the admin dashboard show when each
job last succeeded.

### 4. Cloudflare and reverse proxies

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
  every visitor gets the country `XX` and its rate. The edge must connect to PHP directly (for example nginx with
  PHP-FPM); a proxy in between hides the edge address, so the country header is ignored.
- The Cloudflare ranges are bundled in `config/trustedproxy.php`. Compare them with
  <https://www.cloudflare.com/ips/> before going live and whenever Cloudflare announces changes.
- In Cloudflare use SSL mode "Full (strict)". Ideally the server firewall accepts HTTP(S) only from Cloudflare.

### 5. Backups

`php artisan db:backup` writes a dump of the default database to `storage/app/backups` (or `DB_BACKUP_PATH`) and
keeps the newest 14 (`--keep=14`). It runs daily at 02:30 through the scheduler. The backup directory is made
readable by its owner only (0700) and each backup file 0600. A dump that fails or times out is deleted.

- MySQL / MariaDB: uses `mysqldump --single-transaction`. The credentials are passed in a temporary option file
  readable only by the current user, never on the command line. Without `mysqldump` the command fails with a clear
  message. When the installed `mysqldump` knows `--column-statistics` (MySQL 8 clients), it is called with
  `--column-statistics=0`, so a MySQL 8 client can also dump a MariaDB or older MySQL server.
- SQLite: writes a consistent snapshot with `VACUUM INTO` (SQLite 3.27+), which includes changes still in the WAL
  file. On older SQLite versions it copies the database file with its `-wal` and `-shm` files.

Copy the backups off the server (object storage, another machine): a backup on the same disk does not survive the
loss of that disk.

**Restoring a MySQL backup**

```bash
php artisan down
mysql -h 127.0.0.1 -u linkcash -p linkcash < storage/app/backups/mysql-2026-11-16_023000.sql
php artisan cache:clear      # settings are cached for an hour
php artisan queue:restart
php artisan up
```

`-p` asks for the password, so it never ends up in the shell history. Restore into an empty database (or a new one
first, to check the backup) because the dump recreates the tables.

**Restoring a SQLite backup**: run `php artisan down`, copy the backup file over the database file named in
`DB_DATABASE` (by default `database/database.sqlite`), then `php artisan cache:clear` and `php artisan up`.

### 6. Preflight

Run `php artisan app:preflight` on the server after configuring it. It prints PASS, WARN or FAIL for debug mode, the
environment, the app key, an https `APP_URL`, the secure session cookie, trusted proxies, the database connection,
pending migrations, the storage link, the frontend build, the queue, the scheduler heartbeat, the mailer, captcha keys,
PayPal, SePay and the support email. It exits with a non-zero code when any check fails.

### 7. Before going live

- Have a person review the Terms of Use and Privacy Policy (`resources/views/terms.blade.php`,
  `resources/views/privacy.blade.php`) for your business and jurisdiction.
- Run `php artisan app:preflight` and fix every FAIL.

## First-time admin setup

1. Create an administrator: `php artisan make:admin <username> <email>` (it asks for the password).
2. Sign in at `/auth/signin` and turn on two-factor authentication at `/member/user/two-factor`.
3. Open **Admin > Settings** and fill in:
   - **General**: site name, support email, social links, open or closed registration and maintenance mode.
   - **Email**: the SMTP server and sender. Use "Send test email" to check it, then run `php artisan queue:restart`.
   - **Payments**: minimum withdrawal and top-up, exchange rate, payout methods members may choose, PayPal client ID,
     secret and mode (`live` to collect real money), and the SePay API token and bank account. Run
     `php artisan queue:restart` afterwards.
   - **Traffic**: view counting rules (seconds per step, unique visitor window, review days, visit mode, tutorial
     video) and the default advertiser price.
   - **Captcha**: the provider (Cloudflare Turnstile, hCaptcha or Google reCAPTCHA) and its site and secret keys.
4. Check **Admin > Rates** for the CPM rate of each country and link level.

Secrets saved on these pages are encrypted with `APP_KEY` and override the matching `.env` values.
