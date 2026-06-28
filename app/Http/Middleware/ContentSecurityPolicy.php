<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Content-Security-Policy headers to all HTTP responses.
 *
 * Restricts which resources the browser can load, mitigating XSS attacks.
 * In dev mode, Vite HMR uses a WebSocket connection on the Vite dev server
 * origin, which is permitted via connect-src. Script sources are relaxed
 * to allow Vite's inline HMR scripts and React Fast Refresh.
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
        return app()->environment('local')
            ? $this->getDevPolicy()
            : $this->getProdPolicy();
    }

    /**
     * CSP for local development — permits Vite HMR and React Fast Refresh.
     */
    private function getDevPolicy(): string
    {
        $viteOrigin = env('VITE_DEV_SERVER_URL', 'http://127.0.0.1:5173');

        return "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' {$viteOrigin}; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com; "
            ."img-src 'self' data: https://res.cloudinary.com; "
            ."connect-src 'self' wss: {$viteOrigin} ws://127.0.0.1:5173; "
            ."form-action 'self'; "
            ."font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com https://fonts.googleapis.com";
    }

    /**
     * CSP for production/staging — strict script-src, font CDNs allowed.
     */
    private function getProdPolicy(): string
    {
        return "default-src 'self'; "
            ."script-src 'self'; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com; "
            ."img-src 'self' data: https://res.cloudinary.com; "
            ."connect-src 'self' wss:; "
            ."form-action 'self'; "
            ."font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com https://fonts.googleapis.com";
    }
}
