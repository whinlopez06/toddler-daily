<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ValidateSignature;
use Symfony\Component\HttpFoundation\Response;

class ValidateVerificationSignature extends ValidateSignature
{
    public function handle($request, Closure $next, ...$args): Response
    {
        $publicOrigin = rtrim(config('tracker.frontend_url'), '/');

        if ($request->routeIs('verification.verify') && str_starts_with($publicOrigin, 'https://')) {
            $signedRequest = Request::create($publicOrigin.$request->getRequestUri());

            return parent::handle($signedRequest, fn () => $next($request), ...$args);
        }

        return parent::handle($request, $next, ...$args);
    }
}
