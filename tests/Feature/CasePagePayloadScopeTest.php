<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The staff case page shares one case file across partner agencies. A user
 * who may open the page must still never receive the client's personal
 * record, their notifications, or another agency's timeline entries.
 */
class CasePagePayloadScopeTest extends TestCase
{
    use RefreshDatabase;

    private Agency $ownAgency;

    private Agency $foreignAgency;

    private CaseFile $case;

    private Referral $ownReferral;

    private Referral $foreignReferral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownAgency = Agency::factory()->create();
        $this->foreignAgency = Agency::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->case = CaseFile::factory()->open()->create([
            'user_id' => $manager->id,
            'summary' => 'Confidential narrative',
        ]);

        $this->ownReferral = Referral::factory()->create([
            'case_id' => $this->case->id,
            'agcy_id' => $this->ownAgency->id,
            'status' => 'PROCESSING',
        ]);
        $this->foreignReferral = Referral::factory()->create([
            'case_id' => $this->case->id,
            'agcy_id' => $this->foreignAgency->id,
            'status' => 'PROCESSING',
        ]);

        CaseEvent::create([
            'case_id' => $this->case->id,
            'referral_id' => $this->ownReferral->id,
            'type' => CaseEvent::TYPE_REFERRAL_STATUS_CHANGED,
            'title' => 'Our own update',
            'actor_type' => 'agency',
            'occurred_at' => now()->subMinutes(2),
        ]);
        CaseEvent::create([
            'case_id' => $this->case->id,
            'referral_id' => $this->foreignReferral->id,
            'type' => CaseEvent::TYPE_REFERRAL_STATUS_CHANGED,
            'title' => 'Another agency private update',
            'actor_type' => 'agency',
            'occurred_at' => now()->subMinute(),
        ]);
    }

    private function agencyUser(Agency $agency): User
    {
        return User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => $agency->id,
        ]);
    }

    #[Test]
    public function agency_case_page_carries_only_its_own_timeline_entries(): void
    {
        $response = $this->actingAs($this->agencyUser($this->ownAgency))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            ])
            ->get(route('cases.show', $this->case));

        $response->assertOk();

        $titles = array_column($response->json('props.milestoneTimeline'), 'title');
        $this->assertContains('Our own update', $titles);
        $this->assertNotContains('Another agency private update', $titles);
    }

    #[Test]
    public function agency_case_page_never_receives_the_client_record_or_notifications(): void
    {
        $response = $this->actingAs($this->agencyUser($this->ownAgency))
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            ])
            ->get(route('cases.show', $this->case));

        $response->assertOk()
            ->assertJsonMissingPath('props.caseOverview')
            ->assertJsonMissingPath('props.caseNotifications')
            ->assertJsonMissingPath('props.trackingAgencies');
    }

    #[Test]
    public function an_agency_without_a_referral_on_the_case_is_refused(): void
    {
        $unrelated = Agency::factory()->create();

        $this->actingAs($this->agencyUser($unrelated))
            ->get(route('cases.show', $this->case))
            ->assertForbidden();
    }
}
