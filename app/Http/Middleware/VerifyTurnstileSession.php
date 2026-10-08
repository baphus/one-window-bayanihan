<?php

namespace App\Http\Middleware;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
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
    private const SESSION_KEY = 'turnstile_verified';

    private const SESSION_VERIFIED_AT_KEY = 'turnstile_verified_at';

    public function __construct(private readonly TurnstileVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('turnstile.enabled')) {
            return $next($request);
        }

        // Already verified this session — allow through while the check is fresh
        if ($request->session()->get(self::SESSION_KEY)) {
            $verifiedAt = (int) $request->session()->get(self::SESSION_VERIFIED_AT_KEY, 0);
            $ttl = (int) config('turnstile.session_ttl', 1800);

            if ($verifiedAt > 0 && (time() - $verifiedAt) < $ttl) {
                return $next($request);
            }

            $request->session()->forget([self::SESSION_KEY, self::SESSION_VERIFIED_AT_KEY]);
        }

        $result = $this->verifier->verify(
            $request->input('cf-turnstile-response') ?? $request->input('cf_turnstile_response'),
            $request->ip(),
        );

        if (! $result['ok']) {
            if ($result['unavailable']) {
                return response()->json([
                    'error' => 'turnstile_unavailable',
                    'message' => $result['message'],
                ], 503);
            }

            $missingToken = empty($request->input('cf-turnstile-response') ?? $request->input('cf_turnstile_response'));

            return response()->json([
                'error' => $missingToken ? 'turnstile_required' : 'turnstile_failed',
                'message' => $result['message'],
            ], 422);
        }

        // Mark session as verified
        $request->session()->put(self::SESSION_KEY, true);
        $request->session()->put(self::SESSION_VERIFIED_AT_KEY, time());

        return $next($request);
    }
}
