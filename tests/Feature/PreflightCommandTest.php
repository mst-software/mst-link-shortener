<?php

use App\Services\ScheduleHealth;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/**
 * A server where every check passes: a temporary public directory with a build and a storage link,
 * production configuration, and every scheduled job reported in.
 */
beforeEach(function () {
    $this->publicPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'preflight-public-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->publicPath.'/build');
    File::ensureDirectoryExists($this->publicPath.'/storage');
    File::put($this->publicPath.'/build/manifest.json', '{}');
    $this->app->usePublicPath($this->publicPath);
    $this->app['env'] = 'production';

    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://linkcash.example',
        'session.secure' => true,
        'trustedproxy.proxies' => 'cloudflare',
        'queue.default' => 'sync',
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.linkcash.example',
        'services.turnstile.site_key' => 'site-key',
        'services.turnstile.secret_key' => 'secret-key',
        'services.paypal.client_id' => 'client-id',
        'services.paypal.secret' => 'paypal-secret',
        'services.paypal.mode' => 'live',
        'services.sepay.api_token' => 'sepay-token',
    ]);
    Settings::set('support_email', 'support@linkcash.example');

    foreach (array_keys(ScheduleHealth::JOBS) as $command) {
        app(ScheduleHealth::class)->recordSuccess($command);
    }
});

afterEach(function () {
    File::deleteDirectory($this->publicPath);
});

it('passes on a production ready server', function () {
    $this->artisan('app:preflight')
        ->expectsOutputToContain('PASS  Debug mode')
        ->expectsOutputToContain('PASS  Migrations: No pending migrations.')
        ->expectsOutputToContain('PASS  Queue: QUEUE_CONNECTION is sync')
        ->expectsOutputToContain('PASS  Scheduler')
        ->expectsOutputToContain('All checks passed.')
        ->assertSuccessful();
});

it('fails on a setting that breaks production', function (Closure $breakIt, string $line) {
    $breakIt();

    $this->artisan('app:preflight')
        ->expectsOutputToContain($line)
        ->expectsOutputToContain('1 check(s) failed')
        ->assertFailed();
})->with([
    'debug on' => [fn () => config(['app.debug' => true]), 'FAIL  Debug mode'],
    'no app key' => [fn () => config(['app.key' => '']), 'FAIL  Application key'],
    'plain http url' => [fn () => config(['app.url' => 'http://linkcash.example']), 'FAIL  Application URL'],
    'insecure cookie' => [fn () => config(['session.secure' => false]), 'FAIL  Secure session cookie'],
    'no storage link' => [fn () => File::deleteDirectory(test()->publicPath.'/storage'), 'FAIL  Storage link'],
    'no build' => [fn () => File::delete(test()->publicPath.'/build/manifest.json'), 'FAIL  Frontend build'],
    'log mailer' => [fn () => config(['mail.default' => 'log']), 'FAIL  Mail'],
    'smtp without a host' => [fn () => config(['mail.mailers.smtp.host' => '']), 'FAIL  Mail: SMTP has no host (.env)'],
    'log mailer saved by an admin' => [fn () => Settings::set('mail_mailer', 'log'), 'FAIL  Mail: The mailer is "log" (admin Email settings)'],
    'no captcha secret' => [fn () => config(['services.turnstile.secret_key' => '']), 'FAIL  Captcha'],
    'failing job' => [function () {
        test()->travel(1)->minutes();
        app(ScheduleHealth::class)->recordFailure('views:aggregate', 'Boom.');
    }, 'FAIL  Scheduler: Failing: views:aggregate'],
]);

it('fails when migrations are pending', function () {
    app('migrator')->getRepository()->delete((object) ['migration' => '0001_01_01_000000_create_users_table']);

    $this->artisan('app:preflight')->expectsOutputToContain('FAIL  Migrations: 1 pending migration(s).')->assertFailed();
});

it('warns without failing about optional or advisory settings', function (Closure $changeIt, string $line) {
    $changeIt();

    $this->artisan('app:preflight')
        ->expectsOutputToContain($line)
        ->expectsOutputToContain('No check failed; review the 1 warning(s).')
        ->assertSuccessful();
})->with([
    'local environment' => [fn () => app()->instance('env', 'local'), 'WARN  Environment'],
    'no trusted proxy' => [fn () => config(['trustedproxy.proxies' => '']), 'WARN  Trusted proxies'],
    'queue that needs a worker' => [fn () => config(['queue.default' => 'database']), 'WARN  Queue: QUEUE_CONNECTION is "database"'],
    'paypal sandbox' => [fn () => config(['services.paypal.mode' => 'sandbox']), 'WARN  PayPal'],
    'no sepay token' => [fn () => config(['services.sepay.api_token' => '']), 'WARN  SePay'],
    'any proxy' => [fn () => config(['trustedproxy.proxies' => '*']), 'WARN  Trusted proxies: TRUSTED_PROXIES is "*"'],
    'cloudflare behind another proxy' => [fn () => config(['trustedproxy.proxies' => 'cloudflare,10.0.0.0/8']), 'WARN  Trusted proxies: The cloudflare preset is combined with other proxies'],
    'no support email' => [fn () => Settings::set('support_email', ''), 'WARN  Support email'],
]);

it('warns when a scheduled job is late', function () {
    $this->travel(11)->minutes();
    foreach (array_keys(ScheduleHealth::JOBS) as $command) {
        if ($command !== 'views:aggregate') {
            app(ScheduleHealth::class)->recordSuccess($command);
        }
    }

    $this->artisan('app:preflight')->expectsOutputToContain('WARN  Scheduler: Late or never run: views:aggregate')->assertSuccessful();
});

it('checks the SMTP server an admin saved over a log mailer in .env', function () {
    config(['mail.default' => 'log']);
    Settings::set('mail_mailer', 'smtp');
    Settings::set('mail_host', 'smtp.linkcash.example');

    $this->artisan('app:preflight')->expectsOutputToContain('PASS  Mail: Mailer "smtp" from the admin Email settings.')->assertSuccessful();
});

it('never prints secret values', function () {
    $this->artisan('app:preflight')
        ->doesntExpectOutputToContain('paypal-secret')
        ->doesntExpectOutputToContain('secret-key')
        ->doesntExpectOutputToContain('sepay-token')
        ->assertSuccessful();
});
