<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CookieSecurityTest extends TestCase
{
    #[Test]
    public function cookies_set_during_guest_redirect_are_http_only(): void
    {
        $response = $this->get('/audit-logs');
        $cookies = $response->headers->getCookies();

        $this->assertNotEmpty($cookies);
        $this->assertNotContains(
            'XSRF-TOKEN',
            array_map(fn ($cookie) => $cookie->getName(), $cookies),
        );

        foreach ($cookies as $cookie) {
            $this->assertTrue(
                $cookie->isHttpOnly(),
                "The {$cookie->getName()} cookie must use the HttpOnly flag.",
            );
        }
    }
}
