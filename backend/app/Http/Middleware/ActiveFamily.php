<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveFamily
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->family, 404);
        abort_if($request->user()->family->deletion_requested_at !== null, 423, 'Family deletion is pending.');
        return $next($request);
    }
}
