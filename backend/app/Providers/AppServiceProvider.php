<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\URL::forceRootUrl(config('tracker.frontend_url'));
        if (str_starts_with(config('tracker.frontend_url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(fn ($user, $token) =>
            rtrim(config('tracker.frontend_url'), '/').'/reset-password?token='.rawurlencode($token).'&email='.rawurlencode($user->email)
        );
        config(['mail.mailers.smtp.timeout' => 3]);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event): void {
            if ($demo = $event->user->demoAccount) {
                $demo->update(['last_login_at' => now()]);
                $demo->recordEvent('login');
            }
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event): void {
            $event->user?->demoAccount?->recordEvent('logout');
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSending::class, function ($event) {
            if ($event->channel === 'mail' && ($event->notifiable instanceof \App\Models\User && $event->notifiable->demoAccount)) {
                return false;
            }
        });
        \Illuminate\Support\Facades\RateLimiter::for('maintenance', fn () =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(4)->by('maintenance')
        );
    }
}
