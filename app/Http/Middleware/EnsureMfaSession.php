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

        // Only enforce MFA session for ADMIN accounts
        if (($user = $request->user()) && ! $user->isAdmin()) {
            return $next($request);
        }

        if (config('mfa.login_challenge_enabled') && $user
            && $user->mfa_enabled_at !== null
            && ! $pendingState->hasValidMarker($request, $user)) {
            Auth::guard('web')->logout();
            $pendingState->clear($request);
            $request->session()->invalidate();

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
