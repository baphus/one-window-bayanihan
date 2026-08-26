<?php

namespace Tests\Feature;

use App\Http\Middleware\ContentSecurityPolicy;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every script the application emits must carry the policy nonce.
 *
 * AppServiceProvider calls Vite::prefetch(), which makes @vite emit an extra
 * INLINE loader script. The middleware set the nonce with
 * useScriptTagAttributes(), which decorates <script src> tags only — Laravel's
 * prefetch loader reads $this->nonce, populated exclusively by useCspNonce().
 * The loader therefore shipped unsigned and would be blocked by the enforced
 * policy on every page load.
 */
class ContentSecurityPolicyNonceTest extends TestCase
{
    #[Test]
    public function test_vite_is_given_the_same_nonce_the_policy_advertises(): void
    {
        $response = $this->handleThroughCspMiddleware();

        $nonce = app(Vite::class)->cspNonce();

        $this->assertNotEmpty(
            $nonce,
            'Vite holds no CSP nonce, so the inline prefetch script emitted by '
            .'Vite::prefetch() is unsigned and violates the policy.'
        );

        $this->assertStringContainsString(
            "'nonce-{$nonce}'",
            (string) $response->headers->get('Content-Security-Policy'),
            'The nonce handed to Vite is not the nonce the policy allows.'
        );
    }

    #[Test]
    public function test_blade_and_vite_receive_the_same_nonce(): void
    {
        // @routes (Ziggy) renders its own inline script from the shared view
        // variable. If the two nonces ever diverge, one of them is unsigned.
        $this->handleThroughCspMiddleware();

        $this->assertSame(
            view()->shared('cspNonce'),
            app(Vite::class)->cspNonce(),
            'Ziggy and Vite are signing their inline scripts with different nonces.'
        );
    }

    #[Test]
    public function test_each_response_gets_a_distinct_nonce(): void
    {
        // A reused nonce is a bypassable nonce: an attacker who can read one
        // page can inline a script that the next page's policy will accept.
        $first = $this->cspNonceFromHeader($this->handleThroughCspMiddleware());
        $second = $this->cspNonceFromHeader($this->handleThroughCspMiddleware());

        $this->assertNotSame($first, $second);
    }

    private function handleThroughCspMiddleware(): Response
    {
        return (new ContentSecurityPolicy)->handle(
            Request::create('/'),
            fn () => new Response('ok')
        );
    }

    private function cspNonceFromHeader(Response $response): string
    {
        preg_match(
            "/'nonce-([^']+)'/",
            (string) $response->headers->get('Content-Security-Policy'),
            $m
        );

        $this->assertNotEmpty($m, 'No nonce found in the enforced policy.');

        return $m[1];
    }
}
