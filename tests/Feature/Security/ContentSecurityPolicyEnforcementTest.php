<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentSecurityPolicyEnforcementTest extends TestCase
{
    #[Test]
    public function web_responses_enforce_content_security_policy(): void
    {
        foreach (['/', '/audit-logs'] as $path) {
            $response = $this->get($path);

            $response->assertHeader('Content-Security-Policy');
            $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        }
    }
}
