<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Http\Middleware\LogContext;
use App\Http\Middleware\SecurityHeaders;
use App\Mail\ClientRequestMail;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\ReferralClientAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ReferralInboxSecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_token_is_redacted_from_log_context_url(): void
    {
        $token = 'known-opaque-token-value';

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('withContext')
            ->with($this->callback(function (array $context) use ($token): bool {
                $this->assertStringNotContainsString($token, $context['url']);
                $this->assertStringContainsString('/track/request/[redacted]', $context['url']);

                return true;
            }));
        Log::swap($logger);

        $exchangeRoute = app('router')->getRoutes()->getByName('track.request.exchange');
        $this->assertNotNull($exchangeRoute);
        $this->assertSame(['POST'], $exchangeRoute->methods());
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())
            ->contains(fn ($route) => $route->uri() === 'track/request/{token}'));

        $request = Request::create('/track/request/exchange', 'POST', ['token' => $token]);
        $route = $exchangeRoute;
        $request->setRouteResolver(fn () => $route);

        app(LogContext::class)->handle($request, fn ($request) => new Response('ok'));
    }

    public function test_existing_no_referrer_policy_is_preserved_and_default_is_added(): void
    {
        $middleware = app(SecurityHeaders::class);

        $strict = new Response('ok');
        $strict->headers->set('Referrer-Policy', 'no-referrer');
        $result = $middleware->handle(Request::create('/track/request'), fn () => $strict);
        $this->assertSame('no-referrer', $result->headers->get('Referrer-Policy'));

        $default = $middleware->handle(Request::create('/'), fn () => new Response('ok'));
        $this->assertSame('strict-origin-when-cross-origin', $default->headers->get('Referrer-Policy'));
    }

    public function test_capability_exchange_keeps_no_store_and_no_referrer_headers(): void
    {
        [$agencyUser, $referral, $clientRequest] = $this->requestContext();
        $clientRequest->loadMissing('referral.caseFile.client');
        $issued = app(ReferralClientAccessService::class)->issue(
            $clientRequest,
            $agencyUser,
            ['email' => $clientRequest->referral->caseFile->client->email, 'name' => 'Test Client'],
        );

        $response = $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    public function test_named_agency_limiters_are_scoped_and_link_operations_stop_at_fourth_request(): void
    {
        [$agencyUser, $referral, $clientRequest] = $this->requestContext();
        Mail::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->actingAsWithMfa($agencyUser)
                ->post(route('referrals.client-requests.access.reissue', $clientRequest))
                ->assertRedirect();
        }

        $this->actingAsWithMfa($agencyUser)
            ->post(route('referrals.client-requests.access.reissue', $clientRequest))
            ->assertStatus(429);

        Mail::assertQueued(ClientRequestMail::class, 3);
        $this->assertNotNull(RateLimiter::limiter('agency-client-request-create'));
        $this->assertNotNull(RateLimiter::limiter('agency-client-access'));
        $this->assertNotNull(app('router')->getRoutes()->getByName('referrals.client-requests.access.reissue'));
    }

    public function test_agency_client_limiters_keep_approved_windows_and_key_scopes(): void
    {
        [$agencyUser, $referral] = $this->requestContext();
        $request = Request::create('/');
        $request->setUserResolver(fn () => $agencyUser);
        $request->setRouteResolver(fn () => new class($referral)
        {
            public function __construct(private Referral $referral) {}

            public function parameter(string $key): mixed
            {
                return $key === 'referral' ? $this->referral : null;
            }
        });

        $create = RateLimiter::limiter('agency-client-request-create')($request);
        $access = RateLimiter::limiter('agency-client-access');
        $recipientLimiter = RateLimiter::limiter('agency-client-delivery-recipient');
        $this->assertSame(30, $create->maxAttempts);
        $this->assertSame(3600, $create->decaySeconds);
        $this->assertSame($agencyUser->id.'|'.$referral->id, $create->key);

        $clientRequest = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);
        $request->setRouteResolver(fn () => new class($clientRequest)
        {
            public function __construct(private ReferralClientRequest $clientRequest) {}

            public function parameter(string $key): mixed
            {
                return $key === 'clientRequest' ? $this->clientRequest : null;
            }
        });
        $accessLimit = $access($request);
        $this->assertSame(3, $accessLimit->maxAttempts);
        $this->assertSame(3600, $accessLimit->decaySeconds);
        $this->assertSame($agencyUser->id.'|'.$clientRequest->id, $accessLimit->key);

        $client = $clientRequest->referral->caseFile->client;
        $client->update(['email' => 'Client@example.test']);
        $recipient = $recipientLimiter($request);
        $this->assertSame(20, $recipient->maxAttempts);
        $this->assertSame(3600, $recipient->decaySeconds);
        $this->assertSame('recipient|'.hash('sha256', 'client@example.test'), $recipient->key);

        $client->update(['email' => null]);
        $recipient = $recipientLimiter($request);
        $this->assertSame(20, $recipient->maxAttempts);
        $this->assertSame(3600, $recipient->decaySeconds);
        $this->assertSame('recipient|'.$agencyUser->id.'|'.$request->ip(), $recipient->key);
    }

    public function test_referral_client_tables_are_forced_rls_with_no_client_policy(): void
    {
        $tables = [
            'referral_client_requests',
            'referral_client_request_items',
            'referral_client_access_links',
            'referral_client_messages',
        ];

        foreach ($tables as $table) {
            $row = DB::selectOne('SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = ?::regclass', [$table]);
            $this->assertTrue((bool) $row->relrowsecurity, $table.' should enable RLS');
            $this->assertTrue((bool) $row->relforcerowsecurity, $table.' should force RLS');
        }

        $clientPolicies = DB::table('pg_policies')
            ->whereIn('tablename', $tables)
            ->whereRaw("qual ILIKE '%current_setting%app.user_role%'")
            ->pluck('policyname');
        $this->assertNotEmpty($clientPolicies);
        $this->assertFalse($clientPolicies->contains(fn ($name) => str_contains(strtolower($name), 'public_client')));
    }

    /** @return array{0: User, 1: Referral, 2: ReferralClientRequest} */
    private function requestContext(): array
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
            'is_active' => true,
        ]);
        $owner = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['user_id' => $owner->id, 'client_id' => $client->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        $clientRequest = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);

        return [$agencyUser, $referral, $clientRequest];
    }
}
