<?php

namespace App\Providers;

use App\Actions\Accounts\CreateNewUser;
use App\Actions\Accounts\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        $this->app->bind(\Laravel\Fortify\Contracts\PasswordResetResponse::class, \App\Http\Responses\ConfirmedPasswordResetResponse::class);
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', mb_strtolower(trim((string) $request->input('email'))))->first();
            if (!$user || $user->family?->deletion_requested_at || !Hash::check((string) $request->input('password'), $user->password)) {
                $user?->demoAccount?->recordEvent('login_failed');
                return null;
            }
            $demo = $user->demoAccount;
            if ($demo && (!app(\App\Support\DemoEnvironment::class)->ready() || !$demo->active())) {
                if (!$demo->expires_at->isFuture()) {
                    $demo->recordExpiry();
                }
                throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Demo access has expired or been disabled. Signing in cannot extend it.']);
            }

            return $user;
        });
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('email:'.hash('sha256', mb_strtolower(is_string($r->input('email')) ? trim($r->input('email')) : ''))),
            Limit::perMinute(20)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('api', function (Request $r) {
            $user = $r->user();
            $limits = [Limit::perMinute($user?->demoAccount ? 60 : 120)->by('api:'.($user?->id ?? $r->ip()))];
            if ($user?->demoAccount && !$r->isMethod('GET')) {
                $limits[] = Limit::perMinute(20)->by('demo-writes:'.$user->id);
                $limits[] = Limit::perDay(300)->by('demo-daily-writes:'.$user->id);
            }
            return $limits;
        });
        RateLimiter::for('demo-registration', fn (Request $r) => [
            Limit::perHour(3)->by('demo-ip:'.$r->ip()),
            Limit::perDay(100)->by('demo-global-attempts'),
        ]);
        RateLimiter::for('account-entry', function (Request $r) {
            if ($r->is('auth/register', 'auth/forgot-password', 'auth/reset-password')) {
                return [Limit::perMinute(5)->by('account:'.$r->path().':'.$r->ip()),
                    Limit::perHour(20)->by('account-hour:'.$r->ip())];
            }
            return Limit::perMinute(60)->by('auth:'.$r->ip());
        });
        RateLimiter::for('exports', fn (Request $r) => Limit::perHour(1)->by($r->user()->id));
    }
}
