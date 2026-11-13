<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Content-Security-Policy headers to all web responses.
 *
 * Restricts which resources the browser can load, mitigating XSS attacks.
 * In production/staging, a nonce-based policy is enforced.
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

        // useCspNonce, NOT useScriptTagAttributes.
        //
        // useScriptTagAttributes only decorates the <script src> tags @vite
        // emits. AppServiceProvider calls Vite::prefetch(), which emits an
        // additional INLINE loader script, and Laravel renders that one with
        // Vite::nonceAttribute() — driven by the nonce useCspNonce() sets and
        // nothing else. The loader therefore shipped unsigned and tripped the
        // policy on every page load.
        //
        // useCspNonce covers both: the src tags fall back to the same nonce.
        if (! $isDev) {
            app(Vite::class)->useCspNonce($nonce);
        }

        $response = $next($request);
        $response->headers->remove('Content-Security-Policy-Report-Only');

        if ($isDev) {
            // Dev/local: keep the relaxed policy on the main header
            if (! $response->headers->has('Content-Security-Policy')) {
                $response->headers->set('Content-Security-Policy', $this->getDevPolicy());
            }
        } else {
            // Production/staging: enforce the nonce-based policy.
            if (! $response->headers->has('Content-Security-Policy')) {
                $response->headers->set('Content-Security-Policy', $this->getPolicy($nonce));
                if ($reportUri = config('csp.report_uri')) {
                    $response->headers->set('Reporting-Endpoints', 'csp="'.url($reportUri).'"');
                }
            }
        }

        return $response;
    }

    /**
     * Enforced policy for production/staging — nonce-based script-src and style-src.
     *
     * `'unsafe-inline'` is intentionally absent from style-src. The production
     * Tailwind/Vite build emits a static CSS file (no runtime injection), and
     * react-easy-crop's stylesheet is bundled by Vite as well. Any server-rendered
     * <style> block (e.g. the 503 error page) must carry this nonce attribute.
     * React style={{ }} props use the DOM .style API and are not governed by
     * style-src at all, so they are unaffected.
     */
    private function getPolicy(string $nonce): string
    {
        $reportUri = config('csp.report_uri', '');

        // Explicit WebSocket origin for connect-src. Intentionally not "wss:"
        // (wildcard) — that permits connections to any WSS host and is flagged
        // as a medium-risk CSP misconfiguration. Configure CSP_WEBSOCKET_URL to
        // the specific WSS origin your broadcast server uses (e.g. Pusher or
        // Reverb). If unset, no WebSocket origin is added to the policy.
        $websocketOrigin = (string) config('csp.websocket_url', '');
        $connectSrc = "'self' https://challenges.cloudflare.com"
            .($websocketOrigin !== '' ? " {$websocketOrigin}" : '');

        $policy = "default-src 'self'; "
            // Trust nonce-bearing entry points and the scripts they load (Vite
            // imports/prefetch and Turnstile), not arbitrary same-origin files.
            ."script-src 'nonce-{$nonce}' 'strict-dynamic' https://challenges.cloudflare.com; "
            ."script-src-attr 'none'; "
            ."worker-src 'self'; "
            // Nonce covers any server-rendered <style> blocks (e.g. the 503 page).
            // 'unsafe-inline' is deliberately omitted — the production CSS build
            // is a static file; no runtime stylesheet injection occurs.
            // Material Symbols Outlined is loaded via Google Fonts CDN (too large
            // to self-host without subsetting tooling); all other fonts are self-hosted.
            ."style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com; "
            ."img-src 'self' data: blob: https://res.cloudinary.com; "
            ."connect-src {$connectSrc}; "
            ."frame-src 'self' https://challenges.cloudflare.com https://www.google.com https://maps.google.com; "
            ."object-src 'none'; "
            ."base-uri 'none'; "
            ."frame-ancestors 'none'; "
            ."form-action 'self'; "
            // data: covers base64 inline fonts in PDF/print views.
            // fonts.gstatic.com serves the Material Symbols WOFF2 binary.
            ."font-src 'self' data: https://fonts.gstatic.com";

        if ($reportUri) {
            // Keep report-uri for browsers without Reporting API support.
            $policy .= "; report-uri {$reportUri}; report-to csp";
        }

        return $policy;
    }

    /**
     * CSP for local development — permits Vite HMR and React Fast Refresh.
     * No nonce; uses relaxed directives.
     */
    private function getDevPolicy(): string
    {
        $viteOrigin = (string) config('csp.vite_dev_server_url', 'http://127.0.0.1:5173');
        $appOrigin = config('app.url');

        // Dev-only origins (localhost HMR endpoints) are scoped to this local
        // policy, which is only served when app()->environment('local').
        // Production/staging use getPolicy() and never see these origins.
        $devConnectOrigins = app()->environment('local')
            ? 'http://localhost:8000 http://127.0.0.1:8000 wss: '.$viteOrigin.' ws://127.0.0.1:5173'
            : $viteOrigin;

        return "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' {$viteOrigin} https://challenges.cloudflare.com; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."img-src 'self' data: blob: https://res.cloudinary.com; "
            ."connect-src 'self' {$appOrigin} {$devConnectOrigins} https://challenges.cloudflare.com; "
            ."worker-src 'self' blob:; "
            ."frame-src 'self' https://challenges.cloudflare.com https://www.google.com https://maps.google.com; "
            ."object-src 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'; "
            ."font-src 'self' data: https://fonts.gstatic.com";
    }
}
