<?php

namespace Tests\Feature\Security;

use App\Mail\ClientRequestMail;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\ReferralClientAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReferralClientFragmentExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_get_token_route_is_absent_and_exchange_is_post_only(): void
    {
        $route = app('router')->getRoutes()->getByName('track.request.exchange');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertStringNotContainsString('{token}', $route->uri());
        $this->assertFalse(
            collect(app('router')->getRoutes()->getRoutes())
                ->contains(fn ($candidate) => $candidate->uri() === 'track/request/{token}'),
        );
    }

    public function test_email_magic_link_places_token_only_in_fragment(): void
    {
        [$agencyUser, $clientRequest] = $this->requestContext();
        Mail::fake();

        $this->actingAs($agencyUser)
            ->post(route('referrals.client-requests.access.reissue', $clientRequest))
            ->assertRedirect();

        Mail::assertQueued(ClientRequestMail::class, function (ClientRequestMail $mail): bool {
            $parts = parse_url($mail->magicLink);

            $this->assertSame('/track/request', $parts['path']);
            $this->assertArrayNotHasKey('query', $parts);
            $this->assertStringStartsWith('token=', $parts['fragment'] ?? '');
            $this->assertSame(route('track.request.index'), substr($mail->magicLink, 0, strpos($mail->magicLink, '#')));

            return true;
        });
    }

    public function test_post_exchange_establishes_session_and_redirects_to_token_free_page(): void
    {
        [$agencyUser, $clientRequest] = $this->requestContext();
        $issued = app(ReferralClientAccessService::class)->issue(
            $clientRequest,
            $agencyUser,
            ['email' => $clientRequest->referral->caseFile->client->email, 'name' => 'Test Client'],
        );

        $response = $this->post(route('track.request.exchange'), ['token' => $issued['raw_token']]);

        $response->assertRedirect(route('track.request.index'));
        $response->assertSessionHas('client_request_access.request_id', $clientRequest->id);
        $this->assertStringNotContainsString($issued['raw_token'], $response->headers->get('Location'));
    }

    public function test_no_session_renders_generic_request_access_view_only(): void
    {
        $response = $this->get(route('track.request.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Tracking/RequestAccess')
            ->where('exchangeUrl', route('track.request.exchange'))
            ->missing('token')
            ->missing('request')
            ->missing('case')
            ->missing('clientRequestPanel'));
    }

    public function test_recipient_aggregate_limit_throttles_distinct_users_for_same_client(): void
    {
        [, $agency, $referral, $clientRequest] = $this->makeRequestContext();
        Mail::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
            $this->actingAs($user)->post(route('referrals.client-requests.store', $referral), [
                'type' => ReferralClientRequest::TYPE_QUESTION,
                'title' => 'Question '.$attempt,
                'instructions' => 'Please respond.',
            ])->assertRedirect();
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
            $this->actingAs($user)
                ->post(route('referrals.client-requests.access.reissue', $clientRequest))
                ->assertRedirect();
        }

        $sixthUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $this->actingAs($sixthUser)
            ->post(route('referrals.client-requests.store', $referral), [
                'type' => ReferralClientRequest::TYPE_QUESTION,
                'title' => 'Question six',
                'instructions' => 'Please respond.',
            ])->assertStatus(429);

        Mail::assertQueued(ClientRequestMail::class, 5);
    }

    /** @return array{0: User, 1: ReferralClientRequest} */
    private function requestContext(): array
    {
        [$agencyUser, , , $clientRequest] = $this->makeRequestContext();

        return [$agencyUser, $clientRequest];
    }

    /** @return array{0: User, 1: Agency, 2: Referral, 3: ReferralClientRequest} */
    private function makeRequestContext(): array
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['user_id' => $owner->id, 'client_id' => $client->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        $clientRequest = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);

        return [$agencyUser, $agency, $referral, $clientRequest];
    }

    /** @return array{0: Agency, 1: Referral} */
    private function sharedReferral(): array
    {
        [, $agency, $referral] = $this->makeRequestContext();

        return [$agency, $referral];
    }
}
