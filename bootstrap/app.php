<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IpWhitelist;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPostgresSession;
use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetPostgresSession::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', ''),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->web(append: [
            ContentSecurityPolicy::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'ip.whitelist' => IpWhitelist::class,
            'turnstile' => VerifyTurnstile::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        if (! app()->environment('local')) {
            $exceptions->render(function (NotFoundHttpException $e, Request $request) {
                if ($request->is(['api/*', '*/api/*'])) {
                    return response()->json(['message' => 'Resource not found.'], 404);
                }

                return Inertia::render('Errors/NotFound')->toResponse($request)->setStatusCode(404);
            });
            $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
                if ($request->is(['api/*', '*/api/*'])) {
                    return response()->json(['message' => 'Forbidden.'], 403);
                }

                return Inertia::render('Errors/Forbidden')->toResponse($request)->setStatusCode(403);
            });
            $exceptions->render(function (AuthenticationException $e, Request $request) {
                if ($request->is(['api/*', '*/api/*'])) {
                    return response()->json(['message' => 'Unauthenticated.'], 401);
                }

                return redirect()->guest(route('login'));
            });
            $exceptions->render(function (Throwable $e, Request $request) {
                if ($e instanceof HttpException) {
                    return null;
                }
                Log::error('Unhandled exception', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'user_id' => $request->user()?->id,
                ]);
                if ($request->is(['api/*', '*/api/*'])) {
                    return response()->json(['message' => 'An unexpected error occurred.'], 500);
                }

                return Inertia::render('Errors/ServerError')->toResponse($request)->setStatusCode(500);
            });
        }
    })->create();
