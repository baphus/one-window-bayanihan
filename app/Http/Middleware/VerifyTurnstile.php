<?php

namespace App\Http\Middleware;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloudflare Turnstile verification.
 *
 * Session caching applies only to routes registered with the
 * `turnstile.session` alias (the chatbot) and only when
 * turnstile.session_ttl > 0: one solved challenge then covers a bounded
 * window of messages, and each new window needs a fresh token. Every other
 * route (login, password reset, contact, intake, tracking) verifies a fresh
 * token on every request regardless of that setting, so a pass on one route
 * never unlocks another. Failure rendering follows the request: JSON for
 * API-style requests, redirect-back for web forms.
 */
class VerifyTurnstile
{
    private const SESSION_KEY = 'turnstile_verified';

    private const SESSION_VERIFIED_AT_KEY = 'turnstile_verified_at';

    private const SESSION_ALIAS = 'turnstile.session';

    public function __construct(private readonly TurnstileVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('turnstile.enabled')) {
            return $next($request);
        }

        $ttl = $this->sessionTtl($request);

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

    /**
     * The configured reuse window applies only to the session alias; every
     * other route must present a fresh token per request. 0 (default) also
     * makes the chatbot require a fresh token per message.
     */
    private function sessionTtl(Request $request): int
    {
        $middleware = $request->route()?->gatherMiddleware() ?? [];

        if (! in_array(self::SESSION_ALIAS, $middleware, true)) {
            return 0;
        }

        return max(0, (int) config('turnstile.session_ttl', 0));
    }
}
