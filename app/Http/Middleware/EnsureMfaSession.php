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
        if (config('mfa.login_challenge_enabled') && ($user = $request->user())
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
