<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared Cloudflare Turnstile verification.
 *
 * VerifyTurnstile renders failures per request type; only the failure
 * rendering differs, so it stays in the middleware.
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Verify a Turnstile token.
     *
     * @return array{ok: bool, unavailable: bool, message: string}
     */
    public function verify(?string $token, ?string $ip): array
    {
        if (empty($token)) {
            return ['ok' => false, 'unavailable' => false, 'message' => 'Please complete the security check to continue.'];
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->connectTimeout(3)
                ->post(self::VERIFY_URL, [
                    'secret' => config('turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Turnstile verification request failed', [
                'error' => $e->getMessage(),
                'ip' => $ip,
            ]);

            return ['ok' => false, 'unavailable' => true, 'message' => 'The security check service is temporarily unavailable. Please try again in a moment.'];
        }

        if (! $response->json('success')) {
            $errorCodes = $response->json('error-codes') ?? [];
            Log::warning('Turnstile verification failed', [
                'error_codes' => $errorCodes,
                'ip' => $ip,
            ]);

            return ['ok' => false, 'unavailable' => false, 'message' => $this->errorMessage($errorCodes)];
        }

        return ['ok' => true, 'unavailable' => false, 'message' => ''];
    }

    public function errorMessage(array $errorCodes): string
    {
        if (in_array('timeout-or-duplicate', $errorCodes, true)) {
            return 'Your security check expired. Please complete it again.';
        }

        return 'The security check could not be verified. Please try again.';
    }
}
