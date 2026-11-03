<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class MfaPendingState
{
    public const CHALLENGE_KEY = 'mfa_pending';

    public const USER_KEY = 'pending_mfa_user_id';

    public const ATTEMPTS_KEY = 'mfa_pending_attempts';

    public const SECRET_KEY = 'mfa_pending_secret';

    public const MARKER_KEY = 'mfa_authenticated';

    public const CONFIRMATION_KEY = 'mfa_confirmed';

    /**
     * Routes a required-but-unenrolled user may still reach: enrolment, email
     * verification, access recovery, and logout. Everything else — profile
     * edits, email changes, account deletion, business pages — waits for
     * enrolment.
     */
    public const ENROLLMENT_EXEMPT_ROUTES = [
        'profile.edit',
        'profile.mfa.*',
        'login',
        'login.*',
        'staff.login',
        'staff.login.store',
        'logout',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'register',
        'register.*',
        'register-via-invite.*',
        'verification.*',
    ];

    /** A signed-out marker holder may only end the session or sign in again. */
    public const MARKER_EXEMPT_ROUTES = [
        'profile.mfa.*',
        'login',
        'login.*',
        'staff.login',
        'staff.login.store',
        'logout',
    ];

    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::CHALLENGE_KEY, self::USER_KEY, self::ATTEMPTS_KEY, self::SECRET_KEY,
        ]);
    }

    public function startChallenge(Request $request, User $user, bool $remember, ?string $intended): void
    {
        $this->clear($request);
        $request->session()->forget(self::CONFIRMATION_KEY);
        $request->session()->regenerate();
        $request->session()->put(self::CHALLENGE_KEY, [
            'user_id' => $user->getKey(),
            'remember' => $remember,
            'issued_at' => now()->timestamp,
            'intended_url' => $intended ?: route('dashboard', absolute: false),
            'credential_fingerprint' => hash('sha256', (string) $user->password),
        ]);
        $request->session()->put(self::USER_KEY, $user->getKey());
    }

    public function markAuthenticated(Request $request, User $user): void
    {
        $request->session()->put(self::MARKER_KEY, [
            'user_id' => $user->getKey(),
            'credential_fingerprint' => hash('sha256', (string) $user->password),
        ]);
    }

    public function hasValidMarker(Request $request, User $user): bool
    {
        $marker = $request->session()->get(self::MARKER_KEY);

        return is_array($marker)
            && (string) ($marker['user_id'] ?? '') === (string) $user->getKey()
            && hash_equals((string) ($marker['credential_fingerprint'] ?? ''), hash('sha256', (string) $user->password));
    }

    public function confirm(Request $request, User $user): void
    {
        $request->session()->put(self::CONFIRMATION_KEY, [
            'user_id' => (string) $user->getKey(),
            'credential_fingerprint' => hash('sha256', (string) $user->password),
            'confirmed_at' => now()->timestamp,
        ]);
    }

    public function ensureConfirmed(Request $request, User $user, MfaService $mfa): bool
    {
        if ($this->confirmedUntil($request, $user) !== null) {
            return true;
        }

        if ($request->filled('otp') && $mfa->consumeTotp($user, $request->string('otp')->toString())) {
            $this->confirm($request, $user);

            return true;
        }

        if ($request->filled('recovery_code') && $mfa->consumeRecoveryCode($user, $request->string('recovery_code')->toString())) {
            $this->confirm($request, $user);

            return true;
        }

        return false;
    }

    public function confirmedUntil(Request $request, User $user): ?string
    {
        $confirmation = $request->session()->get(self::CONFIRMATION_KEY);
        $now = now()->timestamp;
        $confirmedAt = is_array($confirmation) ? (int) ($confirmation['confirmed_at'] ?? 0) : 0;

        if (! is_array($confirmation)
            || (string) ($confirmation['user_id'] ?? '') !== (string) $user->getKey()
            || ! hash_equals((string) ($confirmation['credential_fingerprint'] ?? ''), hash('sha256', (string) $user->password))
            || ! $this->hasValidMarker($request, $user)
            || $confirmedAt < 1
            || $confirmedAt > $now
            || $now - $confirmedAt >= config('mfa.fresh_confirmation_ttl', 300)) {
            $request->session()->forget(self::CONFIRMATION_KEY);

            return null;
        }

        return now()->setTimestamp($confirmedAt + config('mfa.fresh_confirmation_ttl', 300))->toIso8601String();
    }
}
