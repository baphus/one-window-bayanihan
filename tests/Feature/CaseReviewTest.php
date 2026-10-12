<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * CM approve/reject against PENDING_REVIEW filings; OFW get 403.
 */
class CaseReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([ThrottleRequests::class]);
    }

    private function createPendingCase(Client $client, User $filer): CaseFile
    {
        return CaseFile::factory()->pendingReview()->create([
            'user_id' => $filer->id,
            'client_id' => $client->id,
        ]);
    }

    // ── approve ─────────────────────────────────────────────────────────────

    public function test_cm_approve_sets_open_and_reviewed_fields(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $client = Client::factory()->create(['user_id' => $filer->id]);
        $filer->client_id = $client->id;
        $filer->save();
        $case = $this->createPendingCase($client, $filer);

        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($cm)->postJson(route('cases.review-approve', $case));
        $response->assertRedirect();

        $case->refresh();
        $this->assertEquals('OPEN', $case->status);
        $this->assertEquals($cm->id, $case->reviewed_by);
        $this->assertNotNull($case->reviewed_at);
    }

    public function test_cm_approve_sends_notification_to_filer(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $client = Client::factory()->create([
            'user_id' => $filer->id,
            'email' => 'filer@example.test',
        ]);
        $filer->client_id = $client->id;
        $filer->save();
        $case = $this->createPendingCase($client, $filer);

        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAs($cm)->postJson(route('cases.review-approve', $case));

        // Approve path records a client-facing event + audit entry for the filer.
        $this->assertDatabaseHas('case_events', [
            'case_id' => $case->id,
            'type' => 'case_opened',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $case->id,
            'action' => 'PUBLISH',
            'module' => 'case',
        ]);
    }

    // ── reject ──────────────────────────────────────────────────────────────

    public function test_cm_reject_soft_deletes_case_and_records_reason(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $client = Client::factory()->create(['user_id' => $filer->id]);
        $filer->client_id = $client->id;
        $filer->save();
        $case = $this->createPendingCase($client, $filer);

        $cm = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $response = $this->actingAs($cm)->postJson(route('cases.review-reject', $case), [
            'reason' => 'Incomplete employment history.',
        ]);
        $response->assertRedirect();

        $case->refresh();
        $this->assertTrue((bool) $case->trashed());
        $this->assertEquals('Incomplete employment history.', $case->deletion_reason);
        $this->assertEquals($cm->id, $case->deleted_by);
    }

    // ── authorization guard ─────────────────────────────────────────────────

    public function test_ofw_cannot_approve_pending_case(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $client = Client::factory()->create(['user_id' => $filer->id]);
        $filer->client_id = $client->id;
        $filer->save();
        $case = $this->createPendingCase($client, $filer);

        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);

        $response = $this->actingAs($ofw)->postJson(route('cases.review-approve', $case));
        $response->assertStatus(403);
    }

    public function test_ofw_cannot_reject_pending_case(): void
    {
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $client = Client::factory()->create(['user_id' => $filer->id]);
        $filer->client_id = $client->id;
        $filer->save();
        $case = $this->createPendingCase($client, $filer);

        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);

        $response = $this->actingAs($ofw)->postJson(route('cases.review-reject', $case), [
            'reason' => 'Wrong.',
        ]);
        $response->assertStatus(403);
    }
}
