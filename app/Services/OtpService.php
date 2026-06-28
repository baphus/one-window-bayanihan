<?php

namespace App\Services;

use App\Mail\OtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        // Send OTP via email (will log when MAIL_MAILER=log)
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
        Log::info('OTP_VERIFY', [
            'key' => $key,
            'sessionId' => $sessionId,
            'cached_value' => $cachedOtp ? substr($cachedOtp, 0, 2).'****' : null,
            'provided_otp' => substr($otp, 0, 2).'****',
        ]);

        // Check if max attempts exceeded — invalidate OTP
        $attempts = Cache::get($attemptsKey, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        $cached = $cachedOtp;

        if (! $cached || $cached !== $otp) {
            // Increment failed-attempt counter
            Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(self::TTL_MINUTES));

            return false;
        }

        // Successful verification — clean up
        Cache::forget($key);
        Cache::forget($attemptsKey);

        return true;
    }
}
