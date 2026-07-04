<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CspHeadersTest extends TestCase
{
    #[Test]
    public function it_sends_content_security_policy_header_on_web_routes(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('Content-Security-Policy');

        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com", $policy);
        $this->assertStringContainsString("img-src 'self' data:", $policy);
        $this->assertStringContainsString('wss:', $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com", $policy);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'testing';
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
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com", $policy);
        $this->assertStringContainsString("img-src 'self' data:", $policy);
        $this->assertStringContainsString('wss:', $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com https://fonts.googleapis.com", $policy);
    }
}
