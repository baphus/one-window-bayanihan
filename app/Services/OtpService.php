<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Settings\SettingRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * How long a passcode is valid.
     *
     * Reads the otp_expiry_seconds setting. The setting existed before this
     * method did — it was seeded as '300' and read by nothing, while a constant
     * hard-coded five minutes. Both now go through here.
     */
    public function ttlMinutes(): int
    {
        return max(1, intdiv($this->ttlSeconds(), 60));
    }

    public function ttlSeconds(): int
    {
        return (int) SettingRegistry::value('otp_expiry_seconds');
    }

    /**
     * How many wrong guesses are accepted before the passcode is discarded.
     */
    public function maxAttempts(): int
    {
        return SettingRegistry::int('otp_max_attempts');
    }

    public function generate(string $identifier, string $purpose = 'default', ?string $sessionId = null): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        [$key, $attemptsKey] = $this->cacheKeys($identifier, $purpose, $sessionId);
        Cache::lock("{$key}:lock", 10)->block(5, function () use ($key, $attemptsKey, $otp): void {
            Cache::put($key, $otp, now()->addSeconds($this->ttlSeconds()));
            Cache::forget($attemptsKey);
        });

        // Send OTP via email. The log mailer writes the full body to the
        // app log; OtpMail redacts the code there (see OtpMail::buildViewData).
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            Mail::to($identifier)->queue(new OtpMail($otp, $purpose));
        }

        return $otp;
    }

    public function verify(string $identifier, string $purpose, string $otp, ?string $sessionId = null): bool
    {
        [$key, $attemptsKey] = $this->cacheKeys($identifier, $purpose, $sessionId);

        return Cache::lock("{$key}:lock", 30)->block(10, function () use ($key, $attemptsKey, $otp): bool {
            $cachedOtp = Cache::get($key);

            // If the code is already gone (expired or consumed), do not count an
            // attempt or touch state — there is nothing left to verify against.
            // Without this, a correct PIN presented after expiry can never succeed
            // because every call keeps incrementing the now-stale counter until the
            // MAX_ATTEMPTS branch forget()/denies.
            if (! is_string($cachedOtp)) {
                return false;
            }

            // Increment-first: each guess consumes an attempt atomically before
            // the code is checked, so concurrent guesses cannot slip past the
            // limit through a read-modify-write race. increment() creates the
            // key when missing without a TTL, so set the expiry on first use
            // to match the OTP lifetime.
            $attempts = (int) Cache::increment($attemptsKey);
            if ($attempts === 1) {
                Cache::put($attemptsKey, 1, now()->addSeconds($this->ttlSeconds()));
            }

            // Exceeding the configured limit invalidates the OTP outright.
            if ($attempts > $this->maxAttempts()) {
                Cache::forget($key);

                return false;
            }

            if (! is_string($cachedOtp) || ! hash_equals($cachedOtp, $otp)) {
                return false;
            }

            // Successful verification — clean up
            Cache::forget($key);
            Cache::forget($attemptsKey);

            return true;
        });
    }

    /** @return array{string, string} */
    private function cacheKeys(string $identifier, string $purpose, ?string $sessionId): array
    {
        $scope = $sessionId ? ":{$sessionId}" : '';

        return [
            "otp:{$purpose}:{$identifier}{$scope}",
            "otp:attempts:{$purpose}:{$identifier}{$scope}",
        ];
    }
}
