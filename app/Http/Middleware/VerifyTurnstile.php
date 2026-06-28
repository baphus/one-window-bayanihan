<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
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

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('turnstile.secret_key'),
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        if (! $response->json('success')) {
            return redirect()->back()
                ->withErrors(['captcha' => 'Verification failed. Please try again.'])
                ->withInput();
        }

        return $next($request);
    }
}
