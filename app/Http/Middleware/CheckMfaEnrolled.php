<?php

namespace App\Http\Middleware;

use App\Services\MfaPendingState;
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
        $isProduction = function_exists('app');
        if ($isProduction) {
            try {
                $isProduction = app()->isProduction();
            } catch (\Throwable $e) {
                $isProduction = false;
            }
        }

        if (! $isProduction && ! config('mfa.enrollment_enforcement_enabled')) {
            return $next($request);
        }

        // Only check authenticated users
        if (! $request->user()) {
            return $next($request);
        }

        // Enrollment policy is intentionally separate from login challenge policy.
        // Single source of truth: User::isInMfaEnforcedRole(). In production it
        // returns true for every role (ADMIN, CASE_MANAGER, AGENCY, OFW), so the
        // production override applies here automatically.
        if (! $request->user()->isInMfaEnforcedRole()) {
            return $next($request);
        }

        // Skip if MFA is already enrolled
        if ($request->user()->mfa_enabled_at !== null) {
            return $next($request);
        }

        // Skip exempt routes. profile.edit is exempt because the middleware
        // redirects there; exempting it prevents a redirect loop when the
        // user lands on the profile page to set up MFA. profile.mfa.* is
        // already exempt to allow the setup AJAX calls to work.
        foreach (MfaPendingState::EXEMPT_ROUTES as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        // Redirect to profile page (the MFA setup UI is triggered by the frontend after getting MFA status)
        // We redirect to the profile page where the frontend will show MFA setup prompt
        return redirect()->route('profile.edit')->with('warning', 'You must enable two-factor authentication before continuing.');
    }
}
