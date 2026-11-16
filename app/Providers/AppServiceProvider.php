<?php

namespace App\Providers;

use App\Contracts\CaptchaVerifier;
use App\Contracts\CountryResolver;
use App\Enums\CaptchaProvider;
use App\Enums\LoginEvent;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Models\User;
use App\Services\LoginRecorder;
use App\Services\Views\FakeCaptchaVerifier;
use App\Services\Views\HeaderCountryResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
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
        RateLimiter::for('link-password', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->route('alias'))));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('two-factor-challenge', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get(TwoFactorChallengeController::SESSION_KEY.'.user_id').'|'.$request->ip()));
        RateLimiter::for('two-factor-code', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->getAuthIdentifier().'|'.$request->ip()));

        ResetPassword::toMailUsing(fn (User $user, string $token): MailMessage => $this->passwordResetMail($user, $token));

        Event::listen(Login::class, fn (Login $event) => $this->recordLoginFootprint($event));
    }

    /**
     * Every sign in fires the guard's Login event (password, two-factor step, trusted device and the sign in
     * right after sign up), so this is the one place that writes the footprint. A user created in this
     * request is recorded as a sign up.
     */
    private function recordLoginFootprint(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $this->app->make(LoginRecorder::class)->record(
            $event->user,
            $event->user->wasRecentlyCreated ? LoginEvent::Signup : LoginEvent::Login,
        );
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
