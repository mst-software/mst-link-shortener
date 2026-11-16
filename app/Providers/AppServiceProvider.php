<?php

namespace App\Providers;

use App\Contracts\CaptchaVerifier;
use App\Contracts\CountryResolver;
use App\Enums\CaptchaProvider;
use App\Models\User;
use App\Services\Views\FakeCaptchaVerifier;
use App\Services\Views\HeaderCountryResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CountryResolver::class, HeaderCountryResolver::class);

        // The provider is an admin setting, so it is resolved per use, not at boot.
        $this->app->bind(CaptchaVerifier::class, function (): CaptchaVerifier {
            if ($this->app->environment('testing')) {
                return new FakeCaptchaVerifier;
            }

            return CaptchaProvider::current()->verifier();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('visit', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('widget-start', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('link-password', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.$request->route('alias')));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        ResetPassword::toMailUsing(fn (User $user, string $token): MailMessage => $this->passwordResetMail($user, $token));
    }

    /**
     * The password reset email, replacing the framework's generic wording.
     */
    private function passwordResetMail(User $user, string $token): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your '.config('app.name').' password')
            ->greeting('Hello '.$user->username.',')
            ->line('We received a request to reset the password for your '.config('app.name').' account.')
            ->action('Reset password', route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]))
            ->line('This link expires in '.$minutes.' minutes.')
            ->line('If you did not ask for a new password, you can ignore this email. Your password will not change.');
    }
}
