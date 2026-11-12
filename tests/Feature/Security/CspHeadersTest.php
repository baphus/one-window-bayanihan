<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CspHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'testing';
    }

    #[Test]
    public function it_sends_enforced_csp_header_on_web_routes(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');

        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("script-src 'nonce-", $policy);
        $this->assertStringContainsString("'strict-dynamic'", $policy);
        // 'unsafe-inline' must be absent from style-src in production — the build
        // emits a static CSS file. Any server-rendered <style> block uses the nonce.
        $this->assertStringNotContainsString("'unsafe-inline'", $policy);
        $this->assertStringContainsString("style-src 'self' 'nonce-", $policy);
        // fonts.bunny.net removed — Figtree, Outfit, Public Sans are self-hosted.
        // fonts.googleapis.com/fonts.gstatic.com remain for Material Symbols Outlined
        // (3.9 MB unsubsetted; impractical to self-host without offline tooling).
        $this->assertStringNotContainsString('fonts.bunny.net', $policy);
        $this->assertStringContainsString('fonts.googleapis.com', $policy);
        $this->assertStringContainsString('fonts.gstatic.com', $policy);
        $this->assertStringContainsString("img-src 'self' data:", $policy);
        // The bare "wss:" wildcard must NOT appear in the production policy — it
        // is a medium-risk CSP misconfiguration (allows WS to any origin).
        // A specific WebSocket host may be injected via CSP_WEBSOCKET_URL; the
        // dev policy retains the wildcard for Vite HMR.
        $this->assertStringNotContainsString('wss:', $policy);
        $this->assertStringContainsString("connect-src 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("font-src 'self' data: https://fonts.gstatic.com", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("base-uri 'none'", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
    }

    #[Test]
    public function it_uses_dev_policy_when_app_env_is_local(): void
    {
        $this->app['env'] = 'local';

        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('Content-Security-Policy');

        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $policy);
        $this->assertStringContainsString('127.0.0.1:5173', $policy);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com", $policy);
        $this->assertStringContainsString("img-src 'self' data:", $policy);
        $this->assertStringContainsString('wss:', $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("font-src 'self' data: https://fonts.gstatic.com", $policy);
        $this->assertStringNotContainsString('fonts.bunny.net', $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
    }
}
