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

        // Unenrolled accounts are CheckMfaEnrolled's concern. Once enrolled,
        // the role no longer matters: every enrolled account needs a marker.
        if (! $user || $user->mfa_enabled_at === null) {
            return $next($request);
        }

        foreach (MfaPendingState::MARKER_EXEMPT_ROUTES as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        if (! $pendingState->hasValidMarker($request, $user)) {
            Auth::guard('web')->logout();
            $pendingState->clear($request);
            $request->session()->invalidate();

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
