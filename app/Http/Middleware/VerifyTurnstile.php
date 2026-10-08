<?php

namespace App\Http\Middleware;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function __construct(private readonly TurnstileVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('turnstile.enabled')) {
            return $next($request);
        }

        $result = $this->verifier->verify(
            $request->input('cf-turnstile-response') ?? $request->input('cf_turnstile_response'),
            $request->ip(),
        );

        if (! $result['ok']) {
            return redirect()->back()
                ->withErrors(['captcha' => $result['message']])
                ->withInput();
        }

        return $next($request);
    }
}
