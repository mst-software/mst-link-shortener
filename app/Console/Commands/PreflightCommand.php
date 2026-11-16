<?php

namespace App\Console\Commands;

use App\Enums\CaptchaProvider;
use App\Enums\PreflightStatus;
use App\Enums\ScheduleStatus;
use App\Services\ScheduleHealth;
use App\Services\Settings;
use App\Support\MailConfigurator;
use App\Support\TrustedProxies;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PreflightCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:preflight';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check that the server is ready for production; exits non-zero when any check fails';

    /**
     * Check name => method that returns its status and an explanation.
     *
     * @var array<string, string>
     */
    private const CHECKS = [
        'Debug mode' => 'checkDebug',
        'Environment' => 'checkEnvironment',
        'Application key' => 'checkAppKey',
        'Application URL' => 'checkAppUrl',
        'Secure session cookie' => 'checkSecureCookie',
        'Trusted proxies' => 'checkTrustedProxies',
        'Database connection' => 'checkDatabase',
        'Migrations' => 'checkMigrations',
        'Storage link' => 'checkStorageLink',
        'Frontend build' => 'checkFrontendBuild',
        'Queue' => 'checkQueue',
        'Scheduler' => 'checkScheduler',
        'Mail' => 'checkMail',
        'Captcha' => 'checkCaptcha',
        'PayPal' => 'checkPaypal',
        'SePay' => 'checkSepay',
        'Support email' => 'checkSupportEmail',
    ];

    public function handle(): int
    {
        $failures = 0;
        $warnings = 0;

        foreach (self::CHECKS as $name => $method) {
            try {
                [$status, $detail] = $this->{$method}();
            } catch (Throwable $exception) {
                [$status, $detail] = [PreflightStatus::Fail, 'Could not be checked: '.ScheduleHealth::summarizeFailure($exception->getMessage())];
            }

            $failures += $status === PreflightStatus::Fail ? 1 : 0;
            $warnings += $status === PreflightStatus::Warn ? 1 : 0;

            $this->line(sprintf('<fg=%s>%s</>  %s: %s', $status->colour(), $status->label(), $name, $detail));
        }

        $this->newLine();

        if ($failures > 0) {
            $this->error($failures.' check(s) failed and '.$warnings.' need attention. Fix the failures before going live.');

            return self::FAILURE;
        }

        $this->info($warnings > 0 ? 'No check failed; review the '.$warnings.' warning(s).' : 'All checks passed.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkDebug(): array
    {
        return config('app.debug')
            ? [PreflightStatus::Fail, 'APP_DEBUG is on, so errors show stack traces and settings. Set APP_DEBUG=false.']
            : [PreflightStatus::Pass, 'APP_DEBUG is off.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkEnvironment(): array
    {
        return $this->laravel->isProduction()
            ? [PreflightStatus::Pass, 'APP_ENV is production.']
            : [PreflightStatus::Warn, 'APP_ENV is "'.$this->laravel->environment().'". Set APP_ENV=production on the live server.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkAppKey(): array
    {
        return filled(config('app.key'))
            ? [PreflightStatus::Pass, 'APP_KEY is set.']
            : [PreflightStatus::Fail, 'APP_KEY is empty. Run php artisan key:generate once and keep the key (it encrypts saved secrets).'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkAppUrl(): array
    {
        $url = (string) config('app.url');

        return Str::startsWith($url, 'https://')
            ? [PreflightStatus::Pass, $url]
            : [PreflightStatus::Fail, 'APP_URL "'.$url.'" is not https. Links in emails and the widget use it.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkSecureCookie(): array
    {
        return config('session.secure')
            ? [PreflightStatus::Pass, 'Session cookies are sent over HTTPS only.']
            : [PreflightStatus::Fail, 'Set SESSION_SECURE_COOKIE=true so the session cookie is never sent over plain HTTP.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkTrustedProxies(): array
    {
        $proxies = TrustedProxies::proxies();

        if ($proxies === []) {
            return [PreflightStatus::Warn, 'TRUSTED_PROXIES is empty. Fine when visitors connect straight to this server; behind Cloudflare or a load balancer set it, or every visitor gets the proxy\'s IP and the country "XX".'];
        }

        if (TrustedProxies::combinesCloudflareWithOtherProxies()) {
            return [PreflightStatus::Warn, 'The cloudflare preset is combined with other proxies. That setup is not supported: requests reach PHP through the other proxy, so CF-Connecting-IP and CF-IPCountry are ignored and every visitor gets the country "XX".'];
        }

        if ($proxies === '*') {
            return [PreflightStatus::Warn, 'TRUSTED_PROXIES is "*", so any server that connects may set the visitor IP. Only safe when PHP is reachable through that proxy alone; prefer listing its IPs or "cloudflare".'];
        }

        return [PreflightStatus::Pass, 'Trusting '.count($proxies).' range(s)'.(TrustedProxies::trustsCloudflare() ? ', the Cloudflare edges.' : '.')];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkDatabase(): array
    {
        DB::connection()->getPdo();

        return [PreflightStatus::Pass, 'Connected to "'.DB::connection()->getName().'".'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkMigrations(): array
    {
        $migrator = $this->laravel->make('migrator');

        if (! $migrator->repositoryExists()) {
            return [PreflightStatus::Fail, 'The database has no migrations table. Run php artisan migrate --force.'];
        }

        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

        return $pending === []
            ? [PreflightStatus::Pass, 'No pending migrations.']
            : [PreflightStatus::Fail, count($pending).' pending migration(s). Run php artisan migrate --force.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkStorageLink(): array
    {
        return file_exists(public_path('storage'))
            ? [PreflightStatus::Pass, 'public/storage exists.']
            : [PreflightStatus::Fail, 'public/storage is missing, so uploaded images saved on this server do not load. Run php artisan storage:link (or create the symlink in the cPanel File Manager), or save uploads on S3-compatible storage.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkFrontendBuild(): array
    {
        return is_file(public_path('build/manifest.json'))
            ? [PreflightStatus::Pass, 'public/build is present.']
            : [PreflightStatus::Fail, 'public/build/manifest.json is missing. Run npm run build locally and upload the public/build folder.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkQueue(): array
    {
        $connection = (string) config('queue.default');

        return $connection === 'sync'
            ? [PreflightStatus::Pass, 'QUEUE_CONNECTION is sync, so any queued work runs right away without a worker.']
            : [PreflightStatus::Warn, 'QUEUE_CONNECTION is "'.$connection.'", but shared hosting runs no queue worker, so queued work would never run. Set QUEUE_CONNECTION=sync.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkScheduler(): array
    {
        $report = app(ScheduleHealth::class)->report();
        $failed = array_filter($report, fn (array $job): bool => $job['status'] === ScheduleStatus::Failed);
        $late = array_filter($report, fn (array $job): bool => $job['status'] === ScheduleStatus::Late);

        if ($failed !== []) {
            return [PreflightStatus::Fail, 'Failing: '.implode(', ', array_column($failed, 'command')).'. See php artisan schedule:health.'];
        }

        if ($late !== []) {
            return [PreflightStatus::Warn, 'Late or never run: '.implode(', ', array_column($late, 'command')).'. Is the cPanel cron job running php artisan schedule:run every minute?'];
        }

        return [PreflightStatus::Pass, 'Every scheduled job reported in on time.'];
    }

    /**
     * Reads the effective mail settings the same way MailConfigurator applies them: once the admin Email settings
     * page has been saved its values win, otherwise .env applies. This does not depend on the settings having been
     * applied to config() when the command booted.
     *
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkMail(): array
    {
        $fromAdmin = MailConfigurator::isConfigured();
        $mailer = $fromAdmin ? Settings::string('mail_mailer', 'log') : (string) config('mail.default');
        $host = $fromAdmin ? Settings::string('mail_host') : (string) config('mail.mailers.smtp.host');
        $source = $fromAdmin ? 'admin Email settings' : '.env';

        if (in_array($mailer, ['log', 'array'], true)) {
            return [PreflightStatus::Fail, 'The mailer is "'.$mailer.'" ('.$source.'), so password resets and notices are never delivered. Set up SMTP on the admin Email settings page.'];
        }

        if ($mailer === 'smtp' && $host === '') {
            return [PreflightStatus::Fail, 'SMTP has no host ('.$source.'). Fill it in on the admin Email settings page.'];
        }

        return [PreflightStatus::Pass, 'Mailer "'.$mailer.'" from the '.$source.'.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkCaptcha(): array
    {
        $provider = CaptchaProvider::current();

        return filled($provider->siteKey()) && filled($provider->secretKey())
            ? [PreflightStatus::Pass, $provider->label().' keys are set.']
            : [PreflightStatus::Fail, $provider->label().' site key or secret key is missing. Add them on the admin Captcha settings page.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkPaypal(): array
    {
        if (blank(config('services.paypal.client_id')) || blank(config('services.paypal.secret'))) {
            return [PreflightStatus::Warn, 'PayPal client ID or secret is missing, so advertisers cannot top up with PayPal. Add them on the admin Payments settings page.'];
        }

        return config('services.paypal.mode') === 'live'
            ? [PreflightStatus::Pass, 'Live credentials are set.']
            : [PreflightStatus::Warn, 'PayPal is in "'.config('services.paypal.mode').'" mode, so no real money is collected.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkSepay(): array
    {
        return filled(config('services.sepay.api_token'))
            ? [PreflightStatus::Pass, 'API token is set.']
            : [PreflightStatus::Warn, 'The SePay API token is missing, so bank transfer top-ups are not matched. Add it on the admin Payments settings page.'];
    }

    /**
     * @return array{0: PreflightStatus, 1: string}
     */
    private function checkSupportEmail(): array
    {
        $email = Settings::string('support_email');

        return $email !== ''
            ? [PreflightStatus::Pass, $email]
            : [PreflightStatus::Warn, 'No support email. Members see it in the footer and the legal pages; set it on the admin General settings page.'];
    }
}
