<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IpWhitelist;
use App\Http\Middleware\LogContext;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPostgresSession;
use App\Http\Middleware\VerifyTurnstile;
use App\Services\IncidentIdService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetPostgresSession::class);
        $middleware->append(LogContext::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
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
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*'])) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            return Inertia::render('Errors/NotFound')->toResponse($request)->setStatusCode(404);
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*']) || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }

            return null; // Let Inertia handle it
        });
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*'])) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            return Inertia::render('Errors/Forbidden')->toResponse($request)->setStatusCode(403);
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*']) || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        });
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*'])) {
                return response()->json(['message' => 'Too many requests. Please slow down.'], 429);
            }

            return inertia('Errors/TooManyRequests', [])->toResponse($request)->setStatusCode(429);
        });
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*'])) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            return null; // Let the default 404 handler take over
        });
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*'])) {
                return response()->json(['message' => 'Method not allowed.'], 405);
            }

            return redirect('/');
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpException || $e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null;
            }

            if (config('app.debug')) {
                if ($request->header('X-Inertia')) {
                    // For Inertia XHR requests, trigger a full page reload so
                    // Laravel's debug error page (Whoops) renders properly instead
                    // of returning unparseable HTML that triggers the React ErrorBoundary.
                    return response('', 409)->header('X-Inertia-Location', $request->fullUrl());
                }

                return null;
            }

            $incidentId = IncidentIdService::generateId();
            Log::error('Unhandled exception', [
                'incident_id' => $incidentId,
                'exception' => $e,
            ]);
            if ($request->is(['api/*', '*/api/*'])) {
                return response()->json([
                    'message' => 'An unexpected error occurred.',
                    'incident_id' => $incidentId,
                ], 500);
            }

            return Inertia::render('Errors/ServerError', ['incidentId' => $incidentId])->toResponse($request)->setStatusCode(500);
        });
    })->create();
