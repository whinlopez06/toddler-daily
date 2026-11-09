<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SchedulerCredential
{
    public function handle(Request $r, Closure $next)
    {
        $secret = config('tracker.maintenance_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32 && hash_equals($secret, (string) $r->bearerToken()), 403);
        abort_if(count($r->all()) > 0, 422, 'This endpoint does not accept a payload.');
        return $next($r);
    }
}
