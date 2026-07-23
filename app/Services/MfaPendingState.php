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

    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::CHALLENGE_KEY, self::USER_KEY, self::ATTEMPTS_KEY, self::SECRET_KEY,
        ]);
    }

    public function startChallenge(Request $request, User $user, bool $remember, ?string $intended): void
    {
        $this->clear($request);
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
}
