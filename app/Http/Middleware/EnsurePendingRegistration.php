<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePendingRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        $pending = $request->session()->get('pending_registration');

        $credentials = is_array($pending) ? ($pending['credentials'] ?? null) : null;
        if (! is_array($credentials) || ! is_string($credentials['email'] ?? null) || $credentials['email'] === ''
            || ! is_string($credentials['password'] ?? null) || $credentials['password'] === ''
            || array_diff(array_keys($pending), ['credentials'])
            || array_diff(array_keys($credentials), ['email', 'password'])) {
            $request->session()->forget('pending_registration');

            return redirect()->route('register');
        }

        return $next($request);
    }
}
