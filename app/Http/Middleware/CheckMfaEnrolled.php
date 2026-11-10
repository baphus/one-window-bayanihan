<?php

namespace App\Http\Middleware;

use App\Services\MfaPendingState;
use App\Services\MfaPolicyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMfaEnrolled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->mfa_enabled_at !== null) {
            return $next($request);
        }

        if (app(MfaPolicyService::class)->effective($user) !== MfaPolicyService::REQUIRED) {
            return $next($request);
        }

        foreach (MfaPendingState::ENROLLMENT_EXEMPT_ROUTES as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        return redirect()->route($user->isOfw() ? 'ofw.profile.edit' : 'profile.edit')
            ->with('warning', 'You must enable two-factor authentication before continuing.');
    }
}
