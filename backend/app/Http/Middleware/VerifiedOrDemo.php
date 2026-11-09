<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class VerifiedOrDemo extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (config('demo.enabled') && $request->user()?->demoAccount?->active()) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
