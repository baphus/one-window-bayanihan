<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\Laravel\Integration as SentryIntegration;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

class LogContext
{
    private static string $requestId = '';

    /**
     * Handle an incoming request.
     *
     * Attaches request metadata (request_id, user context, route, method, URL, IP)
     * to the log context for the duration of the request via Log::withContext(), and
     * mirrors the same safe context onto the Sentry scope so backend exception
     * reports can be correlated with their frontend counterparts.
     */
    public function handle(Request $request, Closure $next): Response
    {
        self::$requestId = (string) Str::uuid();

        // Persist the correlation ID on the request so downstream code can read
        // a single source of truth instead of falling back to a fresh UUID.
        $request->attributes->set('correlation_id', self::$requestId);

        $route = $request->route();
        $user = $request->user();

        Log::withContext([
            'request_id' => self::$requestId,
            'user_id' => $user?->id,
            'user_role' => $user?->role,
            'route' => $route?->getName(),
            'method' => $request->method(),
            'url' => $this->safeUrl($request, $route?->getName()),
            'ip' => $request->ip(),
        ]);

        SentryIntegration::configureScope(function (Scope $scope) use ($request, $route, $user): void {
            $scope->setTag('request_id', self::$requestId);
            $scope->setTag('user_role', $user?->role ?? 'guest');

            if ($user) {
                $scope->setUser(['id' => (string) $user->id]);
            }

            $scope->setContext('request', [
                'route' => $route?->getName(),
                'method' => $request->method(),
            ]);
        });

        $response = $next($request);

        // Expose the correlation ID to the frontend so a browser error report can
        // link back to the backend request that served this page.
        $response->headers->set('X-Request-ID', self::$requestId);

        return $response;
    }

    /** Redact the bearer token while retaining the capability route shape. */
    private function safeUrl(Request $request, ?string $routeName): string
    {
        if ($routeName !== 'track.request.exchange') {
            return $request->fullUrl();
        }

        return preg_replace(
            '#(/track/request/)[^/?\#]+#',
            '$1[redacted]',
            $request->fullUrl(),
        ) ?? '/track/request/[redacted]';
    }

    /**
     * Get the current request's UUID for correlation across log entries.
     */
    public static function getRequestId(): string
    {
        return self::$requestId;
    }
}
