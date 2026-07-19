<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security-related HTTP headers to every response.
 *
 * HSTS is skipped in local environments to avoid breaking plain-HTTP dev servers.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('local')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
            $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $capabilityRoutes = [
            'track.request.exchange',
            'track.request.index',
            'track.request.messages.store',
            'track.request.replacement',
        ];

        if (in_array($request->route()?->getName(), $capabilityRoutes, true)) {
            $response->headers->set('Cache-Control', 'no-store');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        } elseif (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        return $response;
    }
}
