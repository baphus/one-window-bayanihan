<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMfaEnrolled
{
    /**
     * Routes that don't require MFA enrollment check.
     *
     * profile.edit is excluded because the middleware redirects there;
     * excluding it prevents a redirect loop when the user lands on the
     * profile page to set up MFA. profile.mfa.* is already excluded to
     * allow the setup AJAX calls to work.
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

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('mfa.enrollment_enforcement_enabled')) {
            return $next($request);
        }

        // Only check authenticated users
        if (! $request->user()) {
            return $next($request);
        }

        // Enrollment policy is intentionally separate from login challenge policy.
        // Which roles must enrol is configuration, not a hardcoded constant: this
        // check previously covered ADMIN only, so case managers and agency focals
        // reached OFW personal data with a password alone.
        $enforcedRoles = (array) config('mfa.enrollment_enforced_roles', ['ADMIN']);
        if (! in_array($request->user()->role, $enforcedRoles, true)) {
            return $next($request);
        }

        // Skip if MFA is already enrolled
        if ($request->user()->mfa_enabled_at !== null) {
            return $next($request);
        }

        // Skip excluded routes
        foreach ($this->exceptRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        // Redirect to profile page (the MFA setup UI is triggered by the frontend after getting MFA status)
        // We redirect to the profile page where the frontend will show MFA setup prompt
        return redirect()->route('profile.edit')->with('warning', 'You must enable two-factor authentication before continuing.');
    }
}
