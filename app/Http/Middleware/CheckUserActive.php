<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * If the authenticated user has been deactivated or soft-deleted since their
     * session was created, force-logout and redirect to login with a generic message.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = $request->user();

            // Treat NULL as "active"/"not deleted" to avoid accidentally
            // logging out users whose flags were never explicitly set.
            $isInactive = $user && $user->is_active === false;
            $isDeleted = $user && $user->is_deleted === true;

            if ($isInactive || $isDeleted) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}
