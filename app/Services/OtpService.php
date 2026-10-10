<?php

namespace App\Services;

use App\Mail\OtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public const MAX_ATTEMPTS = 5;

    public const TTL_MINUTES = 5;

    public function generate(string $identifier, string $purpose = 'default', ?string $sessionId = null): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = $sessionId
            ? "otp:{$purpose}:{$identifier}:{$sessionId}"
            : "otp:{$purpose}:{$identifier}";
        Cache::put($key, $otp, now()->addMinutes(self::TTL_MINUTES));

        // Reset failed-attempt counter for fresh OTP
        Cache::forget($sessionId
            ? "otp:attempts:{$purpose}:{$identifier}:{$sessionId}"
            : "otp:attempts:{$purpose}:{$identifier}");

        // Send OTP via email. The log mailer writes the full body to the
        // app log; OtpMail redacts the code there (see OtpMail::buildViewData).
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            Mail::to($identifier)->queue(new OtpMail($otp, $purpose));
        }

        return $otp;
    }

    public function verify(string $identifier, string $purpose, string $otp, ?string $sessionId = null): bool
    {
        $key = $sessionId
            ? "otp:{$purpose}:{$identifier}:{$sessionId}"
            : "otp:{$purpose}:{$identifier}";
        $attemptsKey = $sessionId
            ? "otp:attempts:{$purpose}:{$identifier}:{$sessionId}"
            : "otp:attempts:{$purpose}:{$identifier}";

        $cachedOtp = Cache::get($key);

        // Increment-first: each guess consumes an attempt atomically before
        // the code is checked, so concurrent guesses cannot slip past the
        // limit through a read-modify-write race. increment() creates the
        // key when missing without a TTL, so set the expiry on first use
        // to match the OTP lifetime.
        $attempts = (int) Cache::increment($attemptsKey);
        if ($attempts === 1) {
            Cache::put($attemptsKey, 1, now()->addMinutes(self::TTL_MINUTES));
        }

        // Sixth (and later) guesses invalidate the OTP outright.
        if ($attempts > self::MAX_ATTEMPTS) {
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
    }
}
