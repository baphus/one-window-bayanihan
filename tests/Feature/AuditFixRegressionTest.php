<?php

namespace Tests\Feature;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Notifications\MilestoneAdded;
use App\Notifications\ReferralClientRequestActivity;
use App\Notifications\ReferralStatusChanged;
use App\Services\AuditLogger;
use App\Services\Referral\ReferralNotifier;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuditFixRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_anonymous_actor_does_not_inherit_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $anonymousById = AuditLogger::trackCase()->byId(null)->module(AuditModule::CASE)->write();
        $anonymousByModel = AuditLogger::trackCase()->by(null)->module(AuditModule::CASE)->write();
        $implicit = AuditLogger::trackCase()->module(AuditModule::CASE)->write();

        $this->assertNull($anonymousById->fresh()->user_id);
        $this->assertNull($anonymousByModel->fresh()->user_id);
        $this->assertSame($user->id, $implicit->fresh()->user_id);
    }

    public function test_email_correction_revokes_both_tracking_pages(): void
    {
        $case = CaseFile::factory()->create(['email' => 'old@example.test', 'status' => 'OPEN']);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $binding = ['tracker_number' => $case->tracker_number, 'email' => $case->email, 'verified_at' => now()->timestamp];
        $case->update(['email' => 'corrected@example.test']);

        $this->withSession([TrackingService::SESSION_KEY => $binding])
            ->get(route('track.show', ['tracker_number' => $case->tracker_number]))
            ->assertSessionHasErrors('tracker_number')->assertSessionMissing(TrackingService::SESSION_KEY);
        $this->withSession([TrackingService::SESSION_KEY => $binding])
            ->get(route('track.milestones', ['tracker_number' => $case->tracker_number, 'referral' => $referral->id]))
            ->assertNotFound()->assertSessionMissing(TrackingService::SESSION_KEY);
    }

    public function test_self_filed_case_updates_notify_assigned_manager_not_filer(): void
    {
        Notification::fake();
        Mail::fake();
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => $filer->id, 'ofw_user_id' => $filer->id, 'assigned_case_manager_id' => $manager->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $actor = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $referral->agcy_id]);
        $milestone = Milestone::factory()->create(['refr_id' => $referral->id]);

        app(ReferralNotifier::class)->notifyStatusChanged($referral, 'PENDING', 'PROCESSING', $actor->id);
        app(ReferralNotifier::class)->notifyMilestone($referral, $milestone, 'Document checked', $actor->id);

        Notification::assertSentTo($manager, ReferralStatusChanged::class);
        Notification::assertSentTo($manager, MilestoneAdded::class);
        Notification::assertNotSentTo($filer, ReferralStatusChanged::class);
        Notification::assertNotSentTo($filer, MilestoneAdded::class);
    }

    public function test_revision_is_visible_in_owner_inbox_with_owner_request_link(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        $case = CaseFile::factory()->create(['ofw_user_id' => $owner->id, 'email' => $owner->email, 'status' => 'OPEN']);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PROCESSING']);
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $referral->agcy_id, 'is_active' => true]);
        $clientRequest = ReferralClientRequest::factory()->create(['referral_id' => $referral->id, 'creator_user_id' => $agencyUser->id]);
        $message = ReferralClientMessage::create(['request_id' => $clientRequest->id, 'body' => 'My document', 'sender_kind' => ReferralClientMessage::SENDER_CLIENT_USER, 'user_id' => $owner->id, 'kind' => ReferralClientMessage::KIND_MESSAGE]);
        $attachment = $message->attachments()->create(['file_name' => 'passport.pdf', 'file_path' => 'test/passport.pdf', 'file_type' => 'application/pdf', 'size' => 10]);

        $this->actingAsWithMfa($agencyUser)->patch(route('referrals.client-requests.attachments.review', $attachment), ['review_status' => 'NEEDS_REVISION', 'visibility' => 'HIDDEN'])->assertRedirect();

        $this->actingAsWithMfa($owner)->getJson(route('ofw.notifications'))->assertJsonPath('data.0.type', 'client_request_needs_revision')->assertJsonPath('data.0.action_url', route('ofw.case.requests.show', ['case' => $case->id, 'referral' => $referral->id, 'request' => $clientRequest->id]));
        Notification::assertNotSentTo($owner, ReferralClientRequestActivity::class);
    }

    public function test_agency_reply_notifies_assigned_manager_not_original_filer(): void
    {
        Notification::fake();
        $filer = User::factory()->create(['role' => UserRole::OFW->value]);
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['user_id' => $filer->id, 'ofw_user_id' => $filer->id, 'assigned_case_manager_id' => $manager->id, 'status' => 'OPEN']);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'status' => 'PROCESSING']);
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $referral->agcy_id, 'is_active' => true]);
        $clientRequest = ReferralClientRequest::factory()->create(['referral_id' => $referral->id, 'creator_user_id' => $agencyUser->id]);

        $this->actingAsWithMfa($agencyUser)->post(route('referrals.client-requests.messages.store', $clientRequest), ['body' => 'Please check your document.'])->assertRedirect();

        Notification::assertSentTo($manager, ReferralClientRequestActivity::class);
        Notification::assertNotSentTo($filer, ReferralClientRequestActivity::class);
    }
}
