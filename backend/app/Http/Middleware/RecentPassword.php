<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RecentPassword
{
    public function handle(Request $r, Closure $next)
    {
        abort_if(!$r->hasSession() || time() - (int) $r->session()->get('auth.password_confirmed_at', 0) > 600, 423, 'Confirm your password before continuing.');
        return $next($r);
    }
}
