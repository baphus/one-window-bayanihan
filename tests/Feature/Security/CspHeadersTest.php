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
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("img-src 'self' data:", $policy);
        $this->assertStringContainsString("connect-src 'self' wss:", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("font-src 'self' data:", $policy);
    }

    #[Test]
    public function it_does_not_include_unsafe_inline_in_script_src(): void
    {
        $response = $this->get('/login');

        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
    }
}
