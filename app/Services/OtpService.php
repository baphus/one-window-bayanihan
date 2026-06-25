<?php

namespace App\Services;

use App\Mail\OtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public const MAX_ATTEMPTS = 5;

    public const TTL_MINUTES = 5;

    public function generate(string $identifier, string $purpose = 'default'): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = "otp:{$purpose}:{$identifier}";
        Cache::put($key, $otp, now()->addMinutes(self::TTL_MINUTES));

        // Reset failed-attempt counter for fresh OTP
        Cache::forget("otp:attempts:{$purpose}:{$identifier}");

        // Send OTP via email (will log when MAIL_MAILER=log)
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            Mail::to($identifier)->queue(new OtpMail($otp, $purpose));
        }

        return $otp;
    }

    public function verify(string $identifier, string $purpose, string $otp): bool
    {
        $key = "otp:{$purpose}:{$identifier}";
        $attemptsKey = "otp:attempts:{$purpose}:{$identifier}";

        // Check if max attempts exceeded — invalidate OTP
        $attempts = Cache::get($attemptsKey, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        $cached = Cache::get($key);

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
