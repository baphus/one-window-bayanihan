<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Content-Security-Policy headers to all HTTP responses.
 *
 * Restricts which resources the browser can load, mitigating XSS attacks.
 * In dev mode, Vite HMR uses a WebSocket connection on the same origin,
 * which is permitted via `connect-src 'self' wss:`.
 */
class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->getPolicy());
        }

        return $response;
    }

    /**
     * Return the Content-Security-Policy directive string.
     */
    private function getPolicy(): string
    {
        return "default-src 'self'; "
            ."script-src 'self'; "
            ."style-src 'self' 'unsafe-inline'; "
            ."img-src 'self' data:; "
            ."connect-src 'self' wss:; "
            ."form-action 'self'; "
            ."font-src 'self' data:";
    }
}
