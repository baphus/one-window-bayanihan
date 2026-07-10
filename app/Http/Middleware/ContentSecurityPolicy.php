<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Content-Security-Policy headers to all HTTP responses.
 *
 * Restricts which resources the browser can load, mitigating XSS attacks.
 * In production/staging, a nonce-based policy is deployed via the
 * Report-Only header first, allowing violation collection before enforcement.
 * In dev mode, Vite HMR requires relaxed directives.
 */
class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isDev = app()->environment('local');

        $nonce = $isDev ? '' : base64_encode(random_bytes(18));

        // Share nonce with Blade templates for @routes (Ziggy)
        view()->share('cspNonce', $nonce);

        // Set nonce on Vite instance — @vite and @viteReactRefresh
        // read it automatically via useScriptTagAttributes.
        if (! $isDev) {
            app(Vite::class)->useScriptTagAttributes(['nonce' => $nonce]);
        }

        $response = $next($request);

        if ($isDev) {
            // Dev/local: keep the relaxed policy on the main header
            if (! $response->headers->has('Content-Security-Policy')) {
                $response->headers->set('Content-Security-Policy', $this->getDevPolicy());
            }
        } else {
            // Production/staging: deploy nonce-based policy via Report-Only
            if (! $response->headers->has('Content-Security-Policy-Report-Only')) {
                $response->headers->set('Content-Security-Policy-Report-Only', $this->getReportOnlyPolicy($nonce));
            }
        }

        return $response;
    }

    /**
     * Report-Only policy for production/staging — nonce-based script-src.
     *
     * Remove `'unsafe-eval'` from prod script-src (React prod build does not need it).
     * Style `'unsafe-inline'` kept as acceptable tradeoff with Tailwind JIT/MUI.
     */
    private function getReportOnlyPolicy(string $nonce): string
    {
        $reportUri = config('csp.report_uri', '');

        $policy = "default-src 'self'; "
            ."script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com; "
            ."img-src 'self' data: blob: https://res.cloudinary.com; "
            ."connect-src 'self' wss: https://challenges.cloudflare.com; "
            ."frame-src 'self' https://challenges.cloudflare.com https://www.google.com https://maps.google.com; "
            ."object-src 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'; "
            ."font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com https://fonts.googleapis.com";

        if ($reportUri) {
            $policy .= "; report-uri {$reportUri}";
        }

        return $policy;
    }

    /**
     * CSP for local development — permits Vite HMR and React Fast Refresh.
     * No nonce; uses relaxed directives.
     */
    private function getDevPolicy(): string
    {
        $viteOrigin = env('VITE_DEV_SERVER_URL', 'http://127.0.0.1:5173');
        $appOrigin = config('app.url');

        return "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' {$viteOrigin} https://challenges.cloudflare.com; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com; "
            ."img-src 'self' data: blob: https://res.cloudinary.com; "
            ."connect-src 'self' {$appOrigin} http://localhost:8000 http://127.0.0.1:8000 wss: {$viteOrigin} ws://127.0.0.1:5173 https://challenges.cloudflare.com; "
            ."worker-src 'self' blob:; "
            ."frame-src 'self' https://challenges.cloudflare.com https://www.google.com https://maps.google.com; "
            ."object-src 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'; "
            ."font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com https://fonts.googleapis.com";
    }
}
