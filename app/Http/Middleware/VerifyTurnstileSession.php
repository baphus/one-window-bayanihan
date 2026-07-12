<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-level Turnstile verification.
 *
 * Unlike VerifyTurnstile (per-request), this middleware verifies once per session.
 * If the session already has a valid Turnstile verification, subsequent requests pass through.
 * Designed for high-frequency endpoints like the chatbot where per-message verification
 * would degrade UX.
 */
class VerifyTurnstileSession
{
    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const SESSION_KEY = 'turnstile_verified';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('turnstile.enabled')) {
            return $next($request);
        }

        // Already verified this session — allow through
        if ($request->session()->get(self::SESSION_KEY)) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response') ?? $request->input('cf_turnstile_response');

        if (empty($token)) {
            return response()->json([
                'error' => 'turnstile_required',
                'message' => 'Verification required. Please complete the challenge.',
            ], 422);
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
            Log::warning('Turnstile session verification request failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'turnstile_unavailable',
                'message' => 'Verification service unavailable. Please try again.',
            ], 503);
        }

        if (! $response->json('success')) {
            return response()->json([
                'error' => 'turnstile_failed',
                'message' => 'Verification failed. Please try again.',
            ], 422);
        }

        // Mark session as verified
        $request->session()->put(self::SESSION_KEY, true);

        return $next($request);
    }
}
