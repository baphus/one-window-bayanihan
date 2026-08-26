<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent redirect responses from carrying Symfony's generated HTML body.
 */
class StripRedirectResponseBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof RedirectResponse) {
            $response->setContent('');
            $response->headers->set('Content-Length', '0');
        }

        return $response;
    }
}
