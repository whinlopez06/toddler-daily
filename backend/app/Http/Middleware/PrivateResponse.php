<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrivateResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('request_id', (string) Str::uuid());
        $response = $next($request);
        if ($request->is('api/*', 'auth/*', 'sanctum/*', 'internal/*')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Vercel-CDN-Cache-Control', 'no-store');
            $response->headers->set('X-Request-Id', $request->attributes->get('request_id'));
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }
        return $response;
    }
}
