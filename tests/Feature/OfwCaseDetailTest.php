<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
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
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
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
    public function test_ofw_can_view_agency_milestones_for_own_case(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}/agencies/{$referral->id}/milestones");

        $response->assertOk();
        $response->assertJsonPath('component', 'Tracking/AgencyMilestones');
        $this->assertSame($referral->id, $response->json('props.agencyMilestones.referralId') ?? $referral->id);
    }

    #[Test]
    public function test_ofw_cannot_view_milestones_for_another_clients_case(): void
    {
        $otherClient = Client::factory()->create();
        $case = CaseFile::factory()->open()->create([
            'client_id' => $otherClient->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);

        $ofwUser = User::factory()->create([
            'role' => 'OFW',
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
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
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
