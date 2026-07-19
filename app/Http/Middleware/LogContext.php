<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogContext
{
    private static string $requestId = '';

    /**
     * Handle an incoming request.
     *
     * Attaches request metadata (request_id, user context, route, method, URL, IP)
     * to the log context for the duration of the request via Log::withContext().
     */
    public function handle(Request $request, Closure $next): Response
    {
        self::$requestId = (string) Str::uuid();

        $route = $request->route();

        Log::withContext([
            'request_id' => self::$requestId,
            'user_id' => $request->user()?->id,
            'user_role' => $request->user()?->role,
            'route' => $route?->getName(),
            'method' => $request->method(),
            'url' => $this->safeUrl($request, $route?->getName()),
            'ip' => $request->ip(),
        ]);

        return $next($request);
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
