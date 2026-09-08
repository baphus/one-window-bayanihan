<?php

namespace Tests\Feature;

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ScannerHardeningTest extends TestCase
{
    public function test_production_scripts_require_trust_and_reporting_points_to_the_api(): void
    {
        config(['csp.report_uri' => '/api/csp/report']);
        $response = (new ContentSecurityPolicy)->handle(Request::create('/'), fn () => new Response('ok'));
        $policy = $response->headers->get('Content-Security-Policy');
        preg_match('/(?:^|; )script-src ([^;]+)/', $policy, $matches);

        $this->assertStringContainsString("'strict-dynamic'", $matches[1]);
        $this->assertStringNotContainsString("'self'", $matches[1]);
        $this->assertStringNotContainsString("'unsafe-inline'", $matches[1]);
        $this->assertStringNotContainsString("'unsafe-eval'", $matches[1]);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("worker-src 'self'", $policy);
        $this->assertStringContainsString('report-to csp', $policy);
        $this->assertStringContainsString('report-uri /api/csp/report', $policy);
        $this->assertSame('csp="'.url('/api/csp/report').'"', $response->headers->get('Reporting-Endpoints'));
    }

    public function test_reporting_can_be_disabled(): void
    {
        config(['csp.report_uri' => '']);
        $response = (new ContentSecurityPolicy)->handle(Request::create('/'), fn () => new Response('ok'));
        $this->assertFalse($response->headers->has('Reporting-Endpoints'));
        $this->assertStringNotContainsString('report-to', $response->headers->get('Content-Security-Policy'));
    }

    public function test_local_policy_still_supports_hot_reload(): void
    {
        $this->app->instance('env', 'local');
        $response = (new ContentSecurityPolicy)->handle(Request::create('/'), fn () => new Response('ok'));
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'unsafe-eval'", $policy);
        $this->assertStringContainsString('ws://127.0.0.1:5173', $policy);
        $this->assertStringNotContainsString('strict-dynamic', $policy);
    }

    public function test_technology_headers_are_removed_without_losing_security_headers(): void
    {
        $response = (new SecurityHeaders)->handle(Request::create('/'), fn () => new Response('ok', 200, [
            'X-Powered-By' => 'PHP/8.4', 'Server' => 'nginx/1.0', 'Referrer-Policy' => 'no-referrer',
        ]));
        $this->assertFalse($response->headers->has('X-Powered-By'));
        $this->assertFalse($response->headers->has('Server'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_browser_reporting_api_batches_are_recorded(): void
    {
        $this->withoutExceptionHandling();
        Log::shouldReceive('withContext')->andReturnSelf();
        Log::shouldReceive('debug')->once()->withArgs(fn ($message, $context) => $message === 'CSP violation reported'
            && $context['blocked_uri'] === 'inline'
            && $context['effective_directive'] === 'script-src-elem'
        );
        $this->call('POST', '/api/csp/report', [], [], [], ['CONTENT_TYPE' => 'application/reports+json'], json_encode([
            ['type' => 'csp-violation', 'body' => ['blockedURL' => 'inline', 'effectiveDirective' => 'script-src-elem']],
            ['type' => 'unrelated', 'body' => []],
        ]))->assertNoContent();
    }

    public function test_legacy_browser_reports_are_still_recorded(): void
    {
        $this->withoutExceptionHandling();
        Log::shouldReceive('withContext')->andReturnSelf();
        Log::shouldReceive('debug')->once()->withArgs(fn ($message, $context) => $context['blocked_uri'] === 'inline');
        $this->call('POST', '/api/csp/report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode([
            'csp-report' => ['blocked-uri' => 'inline'],
        ]))->assertNoContent();
    }

    public function test_security_contact_and_crawler_files_are_safe(): void
    {
        $security = file_get_contents(public_path('.well-known/security.txt'));
        $this->assertStringContainsString('Contact: mailto:sarsonasjosephuskim@gmail.com', $security);
        preg_match('/Expires: (.+)/', $security, $matches);
        $this->assertGreaterThan(time(), strtotime(trim($matches[1])));
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringNotContainsString('/admin', $robots);
        $this->assertStringContainsString('Disallow: /track', $robots);
        $this->assertStringNotContainsString("Disallow: /\n", $robots);
    }
}
