<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('turnstile.enabled')) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response') ?? $request->input('cf_turnstile_response');

        if (empty($token)) {
            return redirect()->back()
                ->withErrors(['captcha' => 'Please complete the security check to continue.'])
                ->withInput();
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->connectTimeout(3)
                ->post(self::TURNSTILE_VERIFY_URL, [
                    'secret' => config('turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Turnstile verification request failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect()->back()
                ->withErrors(['captcha' => 'The security check service is temporarily unavailable. Please try again in a moment.'])
                ->withInput();
        }

        if (! $response->json('success')) {
            Log::warning('Turnstile verification failed', [
                'error_codes' => $response->json('error-codes') ?? [],
                'ip' => $request->ip(),
            ]);

            return redirect()->back()
                ->withErrors(['captcha' => $this->errorMessage($response->json('error-codes') ?? [])])
                ->withInput();
        }

        return $next($request);
    }

    private function errorMessage(array $errorCodes): string
    {
        if (in_array('timeout-or-duplicate', $errorCodes, true)) {
            return 'Your security check expired. Please complete it again.';
        }

        return 'The security check could not be verified. Please try again.';
    }
}
