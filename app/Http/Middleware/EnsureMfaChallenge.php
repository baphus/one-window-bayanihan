<?php

namespace App\Http\Middleware;

use App\Services\MfaPendingState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $pendingState = app(MfaPendingState::class);
        if (! config('mfa.login_challenge_enabled') || ! $request->session()->has('mfa_pending')) {
            $pendingState->clear($request);

            return redirect()->route('login');
        }

        $pending = $request->session()->get('mfa_pending');
        if (! is_array($pending) || now()->timestamp - (int) ($pending['issued_at'] ?? 0) > config('mfa.pending_ttl')) {
            $pendingState->clear($request);

            return redirect()->route('login')->withErrors(['email' => 'Your MFA challenge expired. Please sign in again.']);
        }

        return $next($request);
    }
}
