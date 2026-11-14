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
            || array_diff(array_keys($pending), ['credentials', 'photo_key'])
            || (isset($pending['photo_key']) && (! is_string($pending['photo_key']) || ! str_starts_with($pending['photo_key'], 'registration-photo:')))
            || array_diff(array_keys($credentials), [
                'email', 'password', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex',
            ])) {
            $request->session()->forget('pending_registration');

            return redirect()->route('register');
        }

        return $next($request);
    }
}
