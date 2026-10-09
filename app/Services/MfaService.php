<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MfaService
{
    private function getRecoveryKey(): string
    {
        return config('app.key');
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', strtoupper(trim($code)), $this->getRecoveryKey());
    }

    /**
     * Generate 8 new recovery codes in plaintext.
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(
                Str::random(4).'-'.Str::random(4).'-'.Str::random(4)
            );
        }

        return $codes;
    }

    /**
     * Hash an array of plaintext recovery codes for storage.
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn ($code) => $this->hashRecoveryCode($code), $codes);
    }

    /**
     * Verify a plaintext code against stored hashed codes.
     */
    public function verifyRecoveryCode(string $code, array $storedHashes): bool
    {
        $hash = $this->hashRecoveryCode($code);

        foreach ($storedHashes as $storedHash) {
            if (hash_equals((string) $storedHash, $hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove a used recovery code (by its plaintext) from stored hashed codes.
     */
    public function removeUsedCode(string $code, array $storedHashes): array
    {
        $hash = $this->hashRecoveryCode($code);

        return array_values(array_filter(
            $storedHashes,
            fn ($h) => $h !== $hash
        ));
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::whereKey($user->getKey())->lockForUpdate()->first();
            if (! $locked || ! is_array($locked->mfa_recovery_codes)) {
                return false;
            }

            $hash = $this->hashRecoveryCode($code);
            $matched = null;
            foreach ($locked->mfa_recovery_codes as $stored) {
                if (hash_equals((string) $stored, $hash)) {
                    $matched = $stored;
                    break;
                }
            }
            if ($matched === null) {
                return false;
            }

            $removed = false;
            $locked->mfa_recovery_codes = array_values(array_filter(
                $locked->mfa_recovery_codes,
                function ($stored) use ($matched, &$removed): bool {
                    if (! $removed && hash_equals((string) $stored, (string) $matched)) {
                        $removed = true;

                        return false;
                    }

                    return true;
                }
            ));
            $locked->save();

            return true;
        });
    }

    public function completeChallenge(string $userId, string $fingerprint, string $code, bool $recovery): ?User
    {
        return DB::transaction(function () use ($userId, $fingerprint, $code, $recovery): ?User {
            $fresh = User::whereKey($userId)->lockForUpdate()->first();
            if (! $fresh || ! $fresh->is_active || $fresh->is_deleted || $fresh->mfa_enabled_at === null
                || ! hash_equals($fingerprint, hash('sha256', (string) $fresh->password))) {
                return null;
            }

            // The replay counter is read and written inside the same row lock.
            // Previously verifyTotp() did its own unlocked UPDATE, so two
            // simultaneous logins presenting the same 30-second code could
            // both read 0, both pass, and both commit — the lock did not cover
            // the write it was supposed to serialise. It still does not cover
            // the recovery-code path, which is already guarded by
            // consumeRecoveryCode()'s own lockForUpdate.
            $lastUsed = (int) ($fresh->mfa_last_totp_counter ?? 0);
            $consumedCounter = null;

            $valid = $recovery
                ? $this->consumeRecoveryCode($fresh, $code)
                : $this->verifyTotp($fresh, $code, $lastUsed, $consumedCounter);

            if ($valid && $consumedCounter !== null && $consumedCounter > $lastUsed) {
                $fresh->mfa_last_totp_counter = $consumedCounter;
                $fresh->save();
            }

            return $valid ? $fresh->fresh() : null;
        });
    }

    public function challengeStillValid(string $userId, string $fingerprint): bool
    {
        $user = User::find($userId);

        return $user !== null && $user->is_active && ! $user->is_deleted
            && $user->mfa_enabled_at !== null
            && hash_equals($fingerprint, hash('sha256', (string) $user->password));
    }

    /**
     * Verify a TOTP code against the counters above $lastUsed and, on success,
     * report which counter was consumed so the caller can persist it under the
     * row lock it already holds.
     *
     * This method never touches the database: the replay guard is only sound if
     * the read and the write happen inside the same locked transaction, which
     * is why completeChallenge() owns both.
     */
    public function verifyTotp(User $user, string $code, int $lastUsed = 0, ?int &$consumedCounter = null): bool
    {
        if (! $user->mfa_secret) {
            return false;
        }

        $google2fa = app('pragmarx.google2fa');
        $step = 30;
        $now = intdiv(now()->timestamp, $step);

        // Highest counter already accepted. A code is accepted for at most
        // (2 * window + 1) steps; anything at or below the last accepted
        // counter is refused, which is strictly tighter than any cache TTL and
        // does not care whether the cache survived a flush.
        $lastUsed = $lastUsed > 0 ? $lastUsed : (int) ($user->mfa_last_totp_counter ?? 0);

        for ($offset = -config('mfa.window'); $offset <= config('mfa.window'); $offset++) {
            $counter = $now + $offset;

            // Never accept an old or already-consumed counter.
            if ($counter <= $lastUsed) {
                continue;
            }

            try {
                $valid = $google2fa->verifyKey($user->mfa_secret, $code, 0, $counter) !== false;
            } catch (Throwable) {
                $valid = false;
            }
            if (! $valid) {
                continue;
            }

            $consumedCounter = $counter;

            // Best-effort fast path for the same counter within the same step.
            // Losing this is harmless — the database check above holds.
            try {
                Cache::add('mfa:totp:'.$user->getKey().':'.$counter, true, config('mfa.replay_ttl', 120));
            } catch (Throwable $e) {
                report($e);
            }

            return true;
        }

        return false;
    }
}
