<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseDocument;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseOwnedPortalReadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_uses_explicit_owner_and_discloses_only_owner_document_metadata(): void
    {
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->pendingReview()->create([
            'ofw_user_id' => $owner->id, 'email' => 'historical@example.test',
            'first_name' => 'Historical', 'last_name' => 'Applicant',
            'staff_summary' => 'PRIVATE STAFF SUMMARY', 'consent_evidence' => 'PRIVATE CONSENT',
            'requested_document_slots' => ['passport_front_page'], 'document_request_reason' => 'Unreadable passport',
        ]);
        $unlinked = CaseFile::factory()->open()->create(['ofw_user_id' => null, 'email' => $owner->email]);
        $document = CaseDocument::factory()->create(['case_id' => $case->id, 'category' => 'passport_front_page']);
        CaseDocument::factory()->create(['case_id' => $case->id, 'category' => 'consent_evidence']);

        $this->actingAsWithMfa($owner)->withHeader('X-Inertia', 'true')->get('/my-cases')
            ->assertOk()->assertJsonCount(1, 'props.cases.data');
        $response = $this->actingAsWithMfa($owner)->withHeader('X-Inertia', 'true')
            ->get(route('ofw.case.show', $case->id))->assertOk();
        $response->assertJsonPath('props.case.first_name', 'Historical')
            ->assertJsonPath('props.case.requested_document_slots', ['passport_front_page'])
            ->assertJsonPath('props.case.document_request_reason', 'Unreadable passport')
            ->assertJsonCount(1, 'props.case.documents')
            ->assertJsonPath('props.case.documents.0.id', $document->id);
        $this->assertArrayNotHasKey('staff_summary', $response->json('props.case'));
        $this->assertArrayNotHasKey('consent_evidence', $response->json('props.case'));
        $this->assertArrayNotHasKey('file_path', $response->json('props.case.documents.0'));
        $this->actingAsWithMfa($owner)->get(route('ofw.case.show', $unlinked->id))->assertForbidden();
    }

    public function test_notifications_follow_case_owner_not_current_or_matching_email(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $other = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->open()->create(['ofw_user_id' => $owner->id, 'email' => $other->email]);
        $notification = CaseNotification::create([
            'case_id' => $case->id, 'client_email' => $other->email,
            'type' => 'case_updated', 'title' => 'Updated', 'message' => 'Case updated.',
        ]);
        $service = app(NotificationService::class);
        $this->assertSame(1, $service->getUnreadCount($owner, 'ofw'));
        $this->assertSame(0, $service->getUnreadCount($other, 'ofw'));
        $this->assertFalse($service->markAsRead($notification->id, 'ofw', $other->email));
        $this->assertFalse($service->markAsRead($notification->id, 'ofw', $other));
        $this->assertTrue($service->markAsRead($notification->id, 'ofw', $owner));
    }

    public function test_notification_contact_comes_from_case_not_account_or_caller(): void
    {
        $case = CaseFile::factory()->open()->create(['email' => 'intake@example.test']);
        $notice = app(NotificationService::class)->notifyOfw(
            $case, 'wrong@example.test', 'case_updated', 'Updated', 'Case updated.', sendEmail: false,
        );
        $this->assertSame('intake@example.test', $notice->client_email);
    }

    public function test_tracking_uses_case_snapshot_without_staff_private_fields(): void
    {
        $case = CaseFile::factory()->open()->create([
            'first_name' => 'Snapshot', 'last_name' => 'Applicant', 'email' => 'snapshot@example.test',
            'address_street' => 'Historical street', 'summary' => 'OFW statement',
            'staff_summary' => 'PRIVATE STAFF SUMMARY', 'consent_evidence' => 'PRIVATE CONSENT',
        ]);
        $service = app(TrackingService::class);
        $this->assertTrue($service->emailMatchesCase($case, ' SNAPSHOT@example.test '));
        $payload = $service->buildTrackingData($case);
        $this->assertSame('Snapshot Applicant', $payload['trackedCase']['clientName']);
        $this->assertSame('OFW statement', $payload['caseOverview']['narrative']);
        $this->assertStringNotContainsString('PRIVATE', json_encode($payload));
    }
}
