<?php

use App\Http\Middleware\CheckMfaEnrolled;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckUserActive;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureMfaChallenge;
use App\Http\Middleware\EnsureMfaSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogContext;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPostgresSession;
use App\Http\Middleware\StripRedirectResponseBody;
use App\Http\Middleware\VerifyTurnstile;
use App\Http\Middleware\VerifyTurnstileSession;
use App\Services\IncidentIdService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
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
        $middleware->append(StripRedirectResponseBody::class);

        // Use browser-provided origin metadata for CSRF protection. Besides
        // rejecting cross-origin writes, this prevents Laravel from emitting
        // the JavaScript-readable XSRF-TOKEN cookie on every web response.
        $middleware->preventRequestForgery(originOnly: true);

        $middleware->trustProxies(
            at: explode(',', env('TRUSTED_PROXIES', '10.0.0.0/8')),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );

        $middleware->web(
            prepend: [ContentSecurityPolicy::class],
            append: [
                CheckUserActive::class,
                EnsureMfaSession::class,
                CheckMfaEnrolled::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );

        $middleware->alias([
            'role' => CheckRole::class,
            'turnstile' => VerifyTurnstile::class,
            'turnstile.session' => VerifyTurnstileSession::class,
            'mfa.pending' => EnsureMfaChallenge::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 503) {
                $retry = $e->getHeaders()['Retry-After'] ?? null;

                if (is_numeric($retry)) {
                    $retryMinutes = (int) ceil((int) $retry / 60);
                } else {
                    $retryMinutes = null;
                }

                return response()->view('errors.503', [
                    'errors' => new ViewErrorBag,
                    'exception' => $e,
                    'retry' => $retryMinutes,
                ], 503, $e->getHeaders());
            }

            return null;
        });

        $statusPages = [
            NotFoundHttpException::class => ['status' => 404, 'message' => 'Resource not found.', 'view' => 'Errors/NotFound', 'expectsJson' => false],
            AccessDeniedHttpException::class => ['status' => 403, 'message' => 'Forbidden.', 'view' => 'Errors/Forbidden', 'expectsJson' => true],
            AuthenticationException::class => ['status' => 401, 'message' => 'Unauthenticated.', 'redirect' => 'login', 'expectsJson' => true],
            TooManyRequestsHttpException::class => ['status' => 429, 'message' => 'Too many requests. Please slow down.', 'view' => 'Errors/TooManyRequests', 'expectsJson' => true],
            MethodNotAllowedHttpException::class => ['status' => 405, 'message' => 'Method not allowed.', 'redirect' => '/', 'expectsJson' => false],
        ];

        foreach ($statusPages as $class => $page) {
            $exceptions->render(function (Throwable $e, Request $request) use ($class, $page) {
                if (! $e instanceof $class) {
                    return null;
                }

                $isApi = $request->is(['api/*', '*/api/*'])
                    || (($page['expectsJson'] ?? false) && $request->expectsJson());

                if ($isApi) {
                    return response()->json(['message' => $page['message']], $page['status']);
                }

                if (($page['redirect'] ?? null) === 'login') {
                    return redirect()->guest(route('login'));
                }

                if (isset($page['redirect'])) {
                    return redirect($page['redirect']);
                }

                return Inertia::render($page['view'])->toResponse($request)->setStatusCode($page['status']);
            });
        }

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*']) || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }

            return null; // Let Inertia handle it
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is(['api/*', '*/api/*'])) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }

            return null; // Let the default 404 handler take over
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpException || $e instanceof ValidationException || $e instanceof AuthenticationException) {
                return null;
            }

            if (config('app.debug')) {
                if ($request->header('X-Inertia')) {
                    // Log the exception so the developer can debug, then force a full
                    // page reload so Laravel's debug error page (Whoops) renders.
                    Log::error('Unhandled exception during Inertia request (debug mode)', [
                        'exception' => (string) $e,
                        'url' => $request->fullUrl(),
                        'method' => $request->method(),
                    ]);

                    return response('', 409)->header('X-Inertia-Location', $request->fullUrl());
                }

                return null;
            }

            $incidentId = IncidentIdService::generateId();
            Log::error('Unhandled exception', [
                'incident_id' => $incidentId,
                'exception' => $e,
            ]);
            if ($request->is(['api/*', '*/api/*']) || $request->expectsJson()) {
                return response()->json([
                    'message' => 'An unexpected error occurred.',
                    'incident_id' => $incidentId,
                ], 500);
            }

            return Inertia::render('Errors/ServerError', ['incidentId' => $incidentId])->toResponse($request)->setStatusCode(500);
        });

        Integration::handles($exceptions);
    })->create();
