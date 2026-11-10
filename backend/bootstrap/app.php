<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            \Illuminate\Support\Facades\Route::post('/internal/maintenance/tick', \App\Http\Controllers\MaintenanceController::class)
                ->middleware([\App\Http\Middleware\SchedulerCredential::class, 'throttle:maintenance']);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\PrivateResponse::class);
        $middleware->statefulApi();
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\ThrottleRequests::class, \App\Http\Middleware\RecentPassword::class);
        $middleware->alias(['active.family' => \App\Http\Middleware\ActiveFamily::class,
            'demo.access' => \App\Http\Middleware\DemoAccess::class,
            'signed' => \App\Http\Middleware\ValidateVerificationSignature::class,
            'verified.or.demo' => \App\Http\Middleware\VerifiedOrDemo::class,
            'recent.password' => \App\Http\Middleware\RecentPassword::class]);
        $middleware->redirectGuestsTo(fn () => '/login');
        if (env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Illuminate\Database\QueryException $error) {
            \Illuminate\Support\Facades\Log::error('A database operation failed.', ['request_id' => request()->attributes->get('request_id')]);
        })->stop();
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'auth/*', 'internal/*') || $request->expectsJson(),
        );
        $exceptions->render(function (\Throwable $error, Request $request) {
            if (!$request->is('api/*', 'internal/*')) return null;
            $status = $error instanceof \Illuminate\Validation\ValidationException ? 422 :
                ($error instanceof \Illuminate\Auth\AuthenticationException ? 401 :
                ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $error->getStatusCode() : 500));
            $code = match ($status) { 401 => 'UNAUTHENTICATED', 404 => 'NOT_FOUND', 409 => 'STALE_VERSION', 422 => 'VALIDATION_FAILED', 423 => 'ACTION_LOCKED', 429 => 'RATE_LIMITED', default => 'HTTP_ERROR' };
            $body = ['message' => $status >= 500 ? 'The server could not complete this request. Try again.' : ($error->getMessage() ?: 'The request could not be completed.'),
                'code' => $status >= 500 ? 'SERVER_ERROR' : $code, 'request_id' => $request->attributes->get('request_id')];
            if ($error instanceof \Illuminate\Validation\ValidationException) $body['errors'] = $error->errors();
            return response()->json($body, $status, $error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $error->getHeaders() : []);
        });
        $exceptions->respond(function ($response) {
            if (request()->is('api/*', 'auth/*', 'sanctum/*', 'internal/*')) {
                $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
                $response->headers->set('Vercel-CDN-Cache-Control', 'no-store');
                $response->headers->set('X-Request-Id', request()->attributes->get('request_id', ''));
            }
            return $response;
        });
    })->create();
