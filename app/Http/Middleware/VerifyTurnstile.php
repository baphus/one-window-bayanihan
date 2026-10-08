<?php

namespace App\Http\Middleware;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloudflare Turnstile verification.
 *
 * Session caching is controlled by turnstile.session_ttl: 0 (default) means
 * every request verifies its token; a positive TTL verifies once per session
 * and reuses the pass while fresh (for high-frequency endpoints like the
 * chatbot). Failure rendering follows the request: JSON for API-style
 * requests, redirect-back for web forms.
 */
class VerifyTurnstile
{
    private const SESSION_KEY = 'turnstile_verified';

    private const SESSION_VERIFIED_AT_KEY = 'turnstile_verified_at';

    public function __construct(private readonly TurnstileVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('turnstile.enabled')) {
            return $next($request);
        }

        $ttl = (int) config('turnstile.session_ttl', 0);

        if ($ttl > 0 && $request->session()->get(self::SESSION_KEY)) {
            $verifiedAt = (int) $request->session()->get(self::SESSION_VERIFIED_AT_KEY, 0);

            if ($verifiedAt > 0 && (time() - $verifiedAt) < $ttl) {
                return $next($request);
            }

            $request->session()->forget([self::SESSION_KEY, self::SESSION_VERIFIED_AT_KEY]);
        }

        $token = $request->input('cf-turnstile-response') ?? $request->input('cf_turnstile_response');
        $result = $this->verifier->verify($token, $request->ip());

        if (! $result['ok']) {
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($result['unavailable']) {
                    return response()->json([
                        'error' => 'turnstile_unavailable',
                        'message' => $result['message'],
                    ], 503);
                }

                return response()->json([
                    'error' => empty($token) ? 'turnstile_required' : 'turnstile_failed',
                    'message' => $result['message'],
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['captcha' => $result['message']])
                ->withInput();
        }

        if ($ttl > 0) {
            $request->session()->put(self::SESSION_KEY, true);
            $request->session()->put(self::SESSION_VERIFIED_AT_KEY, time());
        }

        return $next($request);
    }
}
