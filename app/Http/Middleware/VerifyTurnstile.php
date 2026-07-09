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
                ->withErrors(['captcha' => 'Verification failed. Please try again.'])
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
                ->withErrors(['captcha' => 'Verification service unavailable. Please try again.'])
                ->withInput();
        }

        if (! $response->json('success')) {
            return redirect()->back()
                ->withErrors(['captcha' => 'Verification failed. Please try again.'])
                ->withInput();
        }

        return $next($request);
    }
}
