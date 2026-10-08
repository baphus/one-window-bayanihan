<?php

namespace App\Http\Middleware;

use App\Services\MfaPendingState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $pendingState = app(MfaPendingState::class);

        $user = $request->user();

        if (! $user || ! $user->isInMfaEnforcedRole()) {
            return $next($request);
        }

        // Skip exempt routes — users must be able to manage MFA settings
        // and access auth routes even without a valid MFA session marker.
        foreach (MfaPendingState::EXEMPT_ROUTES as $pattern) {
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
