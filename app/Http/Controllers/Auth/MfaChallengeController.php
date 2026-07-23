<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaChallengeRequest;
use App\Services\MfaPendingState;
use App\Services\MfaService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaChallengeController extends Controller
{
    public function show(Request $request): Response|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['expires_in' => max(0, config('mfa.pending_ttl') - (now()->timestamp - (int) $request->session()->get('mfa_pending.issued_at')))]);
        }

        return Inertia::render('Auth/MfaChallenge');
    }

    public function totp(MfaChallengeRequest $request, MfaService $mfa): RedirectResponse
    {
        return $this->complete($request, $mfa, false);
    }

    public function recovery(MfaChallengeRequest $request, MfaService $mfa): RedirectResponse
    {
        return $this->complete($request, $mfa, true);
    }

    public function cancel(Request $request, MfaPendingState $pendingState): RedirectResponse
    {
        $pendingState->clear($request);

        return redirect()->route('login');
    }

    private function complete(MfaChallengeRequest $request, MfaService $mfa, bool $recovery): RedirectResponse
    {
        $pending = $request->session()->get('mfa_pending');
        $user = $mfa->completeChallenge((string) ($pending['user_id'] ?? ''), (string) ($pending['credential_fingerprint'] ?? ''), $request->string('code')->toString(), $recovery);
        $valid = $user !== null;

        if (! $valid) {
            $stateStillValid = $mfa->challengeStillValid((string) ($pending['user_id'] ?? ''), (string) ($pending['credential_fingerprint'] ?? ''));
            if (! $stateStillValid) {
                app(MfaPendingState::class)->clear($request);

                throw ValidationException::withMessages(['code' => 'Your login state changed. Please sign in again.']);
            }
            $attempts = (int) $request->session()->get('mfa_pending_attempts', 0) + 1;
            $request->session()->put('mfa_pending_attempts', $attempts);
            event(new Failed('web', null, ['mfa' => true]));
            if ($attempts >= config('mfa.max_attempts')) {
                app(MfaPendingState::class)->clear($request);
                throw ValidationException::withMessages(['code' => 'Too many MFA attempts. Please sign in again.']);
            }

            throw ValidationException::withMessages(['code' => 'The MFA code is invalid.']);
        }

        $intended = $pending['intended_url'] ?? route('dashboard', absolute: false);
        $remember = (bool) ($pending['remember'] ?? false);
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        app(MfaPendingState::class)->markAuthenticated($request, $user);
        app(MfaPendingState::class)->clear($request);

        return redirect()->to($intended);
    }
}
