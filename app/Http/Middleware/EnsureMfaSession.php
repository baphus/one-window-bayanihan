<?php

namespace App\Http\Middleware;

use App\Services\MfaPendingState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaSession
{
    /**
     * Routes that must remain accessible even when the MFA session marker
     * is missing — profile/MFA management, auth, and password routes.
     */
    private array $exceptRoutes = [
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'profile.email-change.*',
        'profile.mfa.*',
        'login',
        'login.*',
        'logout',
        'password.*',
        'register',
        'verification.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $pendingState = app(MfaPendingState::class);

        $user = $request->user();

        if (! $user || ! $user->isInMfaEnforcedRole()) {
            return $next($request);
        }

        // Skip excluded routes — users must be able to manage MFA settings
        // and access auth routes even without a valid MFA session marker.
        foreach ($this->exceptRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        if ($user->mfa_enabled_at !== null && ! $pendingState->hasValidMarker($request, $user)) {
            Auth::guard('web')->logout();
            $pendingState->clear($request);
            $request->session()->invalidate();

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
