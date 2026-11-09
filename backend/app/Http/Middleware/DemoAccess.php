<?php

namespace App\Http\Middleware;

use App\Support\DemoEnvironment;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DemoAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Recovery remains available to ordinary accounts on the same site.
        if ($request->is('auth/forgot-password', 'auth/reset-password') && is_string($request->input('email'))) {
            $account = \App\Models\User::where('email', mb_strtolower(trim($request->input('email'))))->first();
            if ($account?->demoAccount) {
                if ($request->is('auth/forgot-password')) {
                    return response()->json(['message' => 'If this address has an eligible account, a reset link has been sent.']);
                }
                throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Password recovery is unavailable for demo accounts.']);
            }
        }

        $demo = $request->user()?->demoAccount()->first();
        if ($demo && ! $request->is('auth/logout')) {
            if (! app(DemoEnvironment::class)->ready() || ! $demo->active()) {
                if (! $demo->expires_at->isFuture()) {
                    $demo->recordExpiry();
                }
                Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return response()->json(['message' => 'Demo access has expired or been disabled. Your visit history has been retained.', 'code' => 'DEMO_EXPIRED'], 401);
            }
            abort_if($request->is('auth/email/*'), 403, 'Demo email addresses are not verified.');
            abort_if($request->is('api/v1/family') && $request->isMethod('DELETE'), 403,
                'Demo access expires automatically. Contact the portfolio owner to request removal of your visit history.');
        }

        return $next($request);
    }
}
