<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\StripRedirectResponseBody;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RedirectResponseSecurityTest extends TestCase
{
    #[Test]
    public function guest_redirect_from_audit_logs_does_not_include_response_content(): void
    {
        $response = $this->get('/audit-logs');

        $response->assertRedirect(route('login'));
        $response->assertHeader('Content-Length', '0');
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function non_redirect_response_content_is_preserved(): void
    {
        $middleware = app(StripRedirectResponseBody::class);

        $response = $middleware->handle(
            Request::create('/example'),
            fn () => new Response('expected content'),
        );

        $this->assertSame('expected content', $response->getContent());
    }
}
