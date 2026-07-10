<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    #[Test]
    public function it_resolves_trusted_proxies_from_configured_cidrs(): void
    {
        // TrustProxies is configured during bootstrap from TRUSTED_PROXIES.
        // phpunit.xml pins private CIDR ranges so this test does not depend on
        // the developer's local .env value.

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
            'HTTP_X_FORWARDED_HOST' => 'proxy.example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/login');

        // 10.0.0.1 is within the trusted 10.0.0.0/8 range,
        // so the forwarded IP 203.0.113.1 should be reported as the client IP
        $this->assertEquals('203.0.113.1', $this->app['request']->getClientIp());
    }

    #[Test]
    public function it_ignores_forwarded_headers_from_untrusted_proxies(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
            'HTTP_X_FORWARDED_HOST' => 'proxy.example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/login');

        $this->assertEquals('198.51.100.2', $this->app['request']->getClientIp());
    }
}
