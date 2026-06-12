<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\InterventionCreated;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DMWInterventionTest extends TestCase
{
    use RefreshDatabase;

    private CaseService $caseService;

    private Agency $dmw;

    private User $caseManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caseService = app(CaseService::class);

        // Create DMW as the default agency (slug is for readability; is_default drives resolution)
        $this->dmw = Agency::create([
            'id' => fake()->uuid(),
            'name' => 'Department of Migrant Workers',
            'short' => 'DMW',
            'slug' => 'dmw',
            'is_default' => true,
        ]);

        $this->caseManager = User::factory()->create([
            'role' => 'CASE_MANAGER',
        ]);
    }

    private function createDraftCase(array $overrides = []): CaseFile
    {
        return CaseFile::create(array_merge([
            'id' => fake()->uuid(),
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.fake()->unique()->randomNumber(4),
            'tracker_number' => 'OWBAP-'.strtoupper(fake()->bothify('???????')),
            'client_type' => 'OFW',
            'status' => 'DRAFT',
            'user_id' => $this->caseManager->id,
            'draft_client_data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'ofw@example.com',
                'consent' => true,
            ],
        ], $overrides));
    }

    // ---------------------------------------------------------------
    //  1.  Auto-creation of intervention referral on publish
    // ---------------------------------------------------------------

    #[Test]
    public function publish_draft_creates_intervention_referral(): void
    {
        $case = $this->createDraftCase();

        $this->caseService->publishDraft($case->id, $this->caseManager->id);

        $this->assertDatabaseHas('referrals', [
            'case_id' => $case->id,
            'type' => 'intervention',
            'status' => 'PROCESSING',
            'agcy_id' => $this->dmw->id,
        ]);
    }

    // ---------------------------------------------------------------
    //  2.  Idempotency — already-existing intervention is not duplicated
    // ---------------------------------------------------------------

    #[Test]
    public function publish_draft_is_idempotent(): void
    {
        $case = $this->createDraftCase();

        // Pre-create an intervention referral so the guard inside
        // createInterventionReferral() kicks in.
        Referral::create([
            'id' => fake()->uuid(),
            'type' => 'intervention',
            'status' => 'PROCESSING',
            'agcy_id' => $this->dmw->id,
            'case_id' => $case->id,
            'required_services' => '',
        ]);

        $this->caseService->publishDraft($case->id, $this->caseManager->id);

        $this->assertEquals(
            1,
            Referral::where('case_id', $case->id)->where('type', 'intervention')->count(),
        );
    }

    // ---------------------------------------------------------------
    //  3.  No OFW notification for auto-created interventions
    // ---------------------------------------------------------------

    #[Test]
    public function publish_draft_does_not_create_ofw_notification(): void
    {
        $case = $this->createDraftCase();

        $this->caseService->publishDraft($case->id, $this->caseManager->id);

        // Intervention auto-referral must NOT create OFW notifications
        $this->assertDatabaseMissing('case_notifications', [
            'case_id' => $case->id,
        ]);
    }

    // ---------------------------------------------------------------
    //  4.  InterventionCreated notification dispatched to DMW users
    // ---------------------------------------------------------------

    #[Test]
    public function publish_draft_creates_dmw_notification(): void
    {
        Notification::fake();

        $dmwUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $this->dmw->id,
            'is_active' => true,
        ]);

        $case = $this->createDraftCase();

        $this->caseService->publishDraft($case->id, $this->caseManager->id);

        Notification::assertSentTo($dmwUser, InterventionCreated::class);
    }

    // ---------------------------------------------------------------
    //  5.  No intervention referral when no default agency exists
    // ---------------------------------------------------------------

    #[Test]
    public function no_intervention_referral_when_no_default_agency(): void
    {
        // Remove the default flag so DefaultAgencyService returns null
        $this->dmw->update(['is_default' => false]);

        $case = $this->createDraftCase();

        $this->caseService->publishDraft($case->id, $this->caseManager->id);

        $this->assertDatabaseMissing('referrals', [
            'case_id' => $case->id,
            'type' => 'intervention',
        ]);
    }

    // ---------------------------------------------------------------
    //  6.  Unarchiving does NOT create a new intervention referral
    // ---------------------------------------------------------------

    #[Test]
    public function unarchive_does_not_create_intervention(): void
    {
        $case = $this->createDraftCase();

        // Publish ⇒ status=OPEN, auto-creates one intervention referral
        $this->caseService->publishDraft($case->id, $this->caseManager->id);

        // Close the case (intervention referrals are excluded from the
        // pending check via canClose, so this succeeds)
        $this->caseService->toggleCaseStatus($case->id, $this->caseManager->id);

        // Archive (requires CLOSED status)
        $this->caseService->archiveCase($case->id, $this->caseManager->id);

        // Unarchive — must NOT create a second intervention referral
        $this->caseService->unarchiveCase($case->id, $this->caseManager->id);

        $this->assertEquals(
            1,
            Referral::where('case_id', $case->id)->where('type', 'intervention')->count(),
        );
    }

    // ---------------------------------------------------------------
    //  7.  Default field values on the intervention referral
    // ---------------------------------------------------------------

    #[Test]
    public function intervention_referral_has_correct_defaults(): void
    {
        $case = $this->createDraftCase();

        $this->caseService->publishDraft($case->id, $this->caseManager->id);

        $this->assertDatabaseHas('referrals', [
            'case_id' => $case->id,
            'type' => 'intervention',
            'status' => 'PROCESSING',
            'agcy_id' => $this->dmw->id,
            'required_services' => '',
        ]);
    }
}
