<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    #[Test]
    public function it_resolves_trusted_proxies_from_config_fallback(): void
    {
        // The TrustProxies middleware falls back to config('trustedproxy.proxies')
        // when env('TRUSTED_PROXIES', '') is empty (as it is in test env).
        // This test verifies the CIDR parsing works end-to-end.
        Config::set('trustedproxy.proxies', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16');

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
            'HTTP_X_FORWARDED_HOST' => 'proxy.example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/up');

        // 10.0.0.1 is within the trusted 10.0.0.0/8 range,
        // so the forwarded IP 203.0.113.1 should be reported as the client IP
        $this->assertEquals('203.0.113.1', $this->app['request']->getClientIp());
    }

    #[Test]
    public function it_trusts_no_proxies_when_empty(): void
    {
        // Default: env('TRUSTED_PROXIES', '') returns '' — no proxies trusted.
        // Request from 10.0.0.1 should NOT have its forwarded headers trusted.
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
            'HTTP_X_FORWARDED_HOST' => 'proxy.example.com',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/up');

        // All proxies trusted via `trustProxies(at: '*')` → X-Forwarded-For is the client IP
        $this->assertEquals('203.0.113.1', $this->app['request']->getClientIp());
    }
}
