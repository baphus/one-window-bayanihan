<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfwCaseDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    #[Test]
    public function test_ofw_sees_case_detail(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}");

        $response->assertOk();
        $response->assertJsonPath('component', 'OFW/CaseDetail');
    }

    #[Test]
    public function test_ofw_case_detail_links_to_authenticated_milestones_route(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
        ]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}");

        $response->assertOk();

        $agencies = $response->json('props.trackingAgencies');
        $this->assertNotEmpty($agencies);
        $this->assertSame(
            route('ofw.case.milestones', ['case' => $case->id, 'referral' => $referral->id]),
            $agencies[0]['milestonesUrl'],
        );
    }

    #[Test]
    public function test_ofw_case_detail_exposes_open_client_request_on_the_agency_card(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        // PROCESSING, not FOR_COMPLIANCE: an agency can send a request without
        // gating the referral, and the portal must still surface it.
        $referral = Referral::factory()->processing()->create(['case_id' => $case->id]);
        $clientRequest = ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'title' => 'Submit passport copy',
            'status' => ReferralClientRequest::STATUS_OPEN,
        ]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}");

        $response->assertOk();

        $card = $response->json('props.trackingAgencies')[0];
        $this->assertSame($clientRequest->id, $card['openRequest']['id']);
        $this->assertSame('Submit passport copy', $card['openRequest']['title']);
        $this->assertSame(ReferralClientRequest::STATUS_OPEN, $card['openRequest']['status']);
        $this->assertSame(
            route('ofw.case.requests.show', [
                'case' => $case->id,
                'referral' => $referral->id,
                'request' => $clientRequest->id,
            ]),
            $card['openRequest']['url'],
        );
        // The card links to the request page, which is the only surface that
        // renders instructions, messages or attachments.
        $this->assertSame(
            ['id', 'title', 'type', 'status', 'dueAt', 'requestedAt', 'url'],
            array_keys($card['openRequest']),
        );
    }

    #[Test]
    public function test_ofw_case_detail_hides_terminal_client_requests_from_the_agency_card(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->processing()->create(['case_id' => $case->id]);
        ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'status' => ReferralClientRequest::STATUS_COMPLETED,
        ]);
        ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'status' => ReferralClientRequest::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}");

        $response->assertOk();
        $this->assertNull($response->json('props.trackingAgencies')[0]['openRequest']);
    }

    #[Test]
    public function test_ofw_case_detail_does_not_expose_another_clients_client_request(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->processing()->create(['case_id' => $case->id]);

        $otherCase = CaseFile::factory()->open()->create([
            'client_id' => Client::factory()->create()->id,
        ]);
        $otherReferral = Referral::factory()->processing()->create(['case_id' => $otherCase->id]);
        $foreignRequest = ReferralClientRequest::factory()->create([
            'referral_id' => $otherReferral->id,
            'title' => 'Foreign request',
        ]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}");

        $response->assertOk();
        $this->assertNull($response->json('props.trackingAgencies')[0]['openRequest']);
        $response->assertDontSee($foreignRequest->id);
        $response->assertDontSee('Foreign request');
    }

    #[Test]
    public function test_ofw_request_projection_does_not_add_a_query_per_referral(): void
    {
        $client = Client::factory()->create();
        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        foreach (range(1, 5) as $ignored) {
            $referral = Referral::factory()->processing()->create(['case_id' => $case->id]);
            ReferralClientRequest::factory()->create([
                'referral_id' => $referral->id,
                'status' => ReferralClientRequest::STATUS_OPEN,
            ]);
        }

        $loaded = CaseFile::with([
            'client.addresses',
            'client.employments',
            'client.nextOfKin',
            'referrals.agency',
            'referrals.services',
            'referrals.milestones.user',
            'category',
        ])->findOrFail($case->id);

        // The eager loads above happen before the log is enabled, so only the
        // queries issued by buildTrackingData() are counted.
        DB::enableQueryLog();
        $payload = app(TrackingService::class)->buildTrackingData($loaded, forOfwPortal: true);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertTrue(collect($payload['trackingAgencies'])->every(
            fn (array $card) => $card['openRequest'] !== null
        ));
        // One request read for the whole case, and no per-referral re-read of
        // the case row behind the "is this still writable" check.
        $this->assertSame(1, $queries->filter(
            fn (string $sql) => str_contains($sql, 'referral_client_requests')
        )->count());
        $this->assertSame(0, $queries->filter(
            fn (string $sql) => str_contains($sql, 'from "cases"')
        )->count());
    }

    /**
     * Every case in which the card must show no request: the write-eligibility
     * rule the reply endpoint enforces is the gate, not the request row alone.
     */
    #[Test]
    public function test_ofw_agency_card_withholds_request_when_it_cannot_be_answered(): void
    {
        $scenarios = [
            'closed case' => fn (CaseFile $case, Referral $referral) => $case->update([
                'status' => 'CLOSED',
                'closed_at' => now(),
            ]),
            'terminal referral' => fn (CaseFile $case, Referral $referral) => $referral->update([
                'status' => 'COMPLETED',
            ]),
            'rejected referral' => fn (CaseFile $case, Referral $referral) => $referral->update([
                'status' => 'REJECTED',
            ]),
            'deleted request' => fn (CaseFile $case, Referral $referral) => $referral->clientRequests()
                ->first()
                ->delete(),
        ];

        foreach ($scenarios as $label => $arrange) {
            $client = Client::factory()->create();
            $ofwUser = User::factory()->create([
                'role' => UserRole::OFW->value,
                'client_id' => $client->id,
            ]);
            $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
            $referral = Referral::factory()->processing()->create(['case_id' => $case->id]);
            ReferralClientRequest::factory()->create([
                'referral_id' => $referral->id,
                'status' => ReferralClientRequest::STATUS_OPEN,
            ]);

            $arrange($case, $referral);

            $response = $this->actingAs($ofwUser)
                ->withHeader('X-Inertia', 'true')
                ->get("/my-cases/{$case->id}");

            $response->assertOk();
            $this->assertNull(
                $response->json('props.trackingAgencies')[0]['openRequest'],
                "Expected no actionable request for scenario: {$label}",
            );
        }
    }

    #[Test]
    public function test_ofw_case_payload_does_not_ship_client_requests_through_the_case_prop(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->processing()->create(['case_id' => $case->id]);
        ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'title' => 'Sensitive request body',
            'instructions' => 'Private instructions that belong on the request page only.',
            'status' => ReferralClientRequest::STATUS_OPEN,
        ]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}");

        $response->assertOk();

        // The projection must not be built by attaching relations to models the
        // controller also serializes: `case` is its own prop, so an eager-loaded
        // referrals.clientRequests (or a re-attached caseFile) would ship every
        // request row to the browser inside it.
        $caseProp = $response->json('props.case');
        $this->assertArrayNotHasKey('client_requests', $caseProp);
        $this->assertArrayNotHasKey('clientRequests', $caseProp['referrals'][0]);
        $this->assertArrayNotHasKey('case_file', $caseProp['referrals'][0]);

        // The request is reachable only through the card's link, never inlined.
        $response->assertDontSee('Private instructions that belong on the request page only.');
        $this->assertSame(
            'Sensitive request body',
            $response->json('props.trackingAgencies')[0]['openRequest']['title'],
        );
    }

    #[Test]
    public function test_public_tracking_payload_carries_no_client_request_projection(): void
    {
        $client = Client::factory()->create();
        $case = CaseFile::factory()->open()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->processing()->create(['case_id' => $case->id]);
        ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'title' => 'Agency-only request title',
            'instructions' => 'Instructions that stay behind the request page.',
            'status' => ReferralClientRequest::STATUS_OPEN,
        ]);

        // Service level: the key is absent, not merely null.
        $card = app(TrackingService::class)->buildTrackingData($case->fresh())['trackingAgencies'][0];
        $this->assertArrayNotHasKey('openRequest', $card);

        // Route level: an anonymous visitor with a valid OTP binding sees the
        // timeline but never the request.
        $response = $this->withSession([
            TrackingService::SESSION_KEY => [
                'tracker_number' => $case->tracker_number,
                'email' => $client->email,
                'verified_at' => now()->getTimestamp(),
            ],
        ])->get(route('track.show', ['tracker_number' => $case->tracker_number]));

        $response->assertOk();
        $response->assertDontSee('Agency-only request title');
        $response->assertDontSee('Instructions that stay behind the request page.');
        // Inertia embeds the page props as JSON in the response, so the key
        // itself must be absent from the public page.
        $response->assertDontSee('openRequest');
    }

    #[Test]
    public function test_ofw_can_view_agency_milestones_for_own_case(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
        ]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}/agencies/{$referral->id}/milestones");

        $response->assertOk();
        $response->assertJsonPath('component', 'Tracking/AgencyMilestones');
        $this->assertSame($referral->id, $response->json('props.agencyMilestones.referralId'));
    }

    #[Test]
    public function test_ofw_cannot_view_milestones_for_another_clients_case(): void
    {
        $otherClient = Client::factory()->create();
        $case = CaseFile::factory()->open()->create([
            'client_id' => $otherClient->id,
        ]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);

        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => Client::factory()->create()->id,
        ]);

        $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}/agencies/{$referral->id}/milestones")
            ->assertForbidden();
    }

    #[Test]
    public function test_ofw_milestones_route_rejects_referral_from_another_case(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
        ]);
        $otherCase = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
        ]);
        $referral = Referral::factory()->create(['case_id' => $otherCase->id]);

        $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}/agencies/{$referral->id}/milestones")
            ->assertNotFound();
    }
}
