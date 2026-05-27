<?php

namespace App\Services;

use App\Mail\OtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function generate(string $identifier, string $purpose = 'default'): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $key = "otp:{$purpose}:{$identifier}";
        Cache::put($key, $otp, now()->addMinutes(5));

        // Send OTP via email (will log when MAIL_MAILER=log)
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            Mail::to($identifier)->queue(new OtpMail($otp, $purpose));
        }

        return $otp;
    }

    public function verify(string $identifier, string $purpose, string $otp): bool
    {
        $key = "otp:{$purpose}:{$identifier}";
        $cached = Cache::get($key);

        if (! $cached || $cached !== $otp) {
            return false;
        }

        Cache::forget($key);

        return true;
    }
}
