<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfwAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isOfw() || $request->routeIs('logout')) {
            return $next($request);
        }

        if ($user->must_change_password) {
            if ($request->routeIs('password.temporary.*')) {
                return $next($request);
            }

            return redirect()->route('password.temporary.edit');
        }

        if (! $user->hasVerifiedEmail() && ! $request->routeIs('verification.*')) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
