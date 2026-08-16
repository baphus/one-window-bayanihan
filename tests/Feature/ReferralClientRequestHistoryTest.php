<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralClientRequestHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_exposes_safe_history_and_role_permissions(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $owner->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        $clientRequest = ReferralClientRequest::factory()->create([
            'referral_id' => $referral->id,
            'creator_user_id' => $agencyUser->id,
        ]);
        $link = ReferralClientAccessLink::factory()->create([
            'request_id' => $clientRequest->id,
            'token_hash' => hash('sha256', 'raw-secret-token'),
            'recipient_snapshot' => ['email' => 'client@example.test'],
        ]);
        ReferralClientMessage::factory()->create([
            'request_id' => $clientRequest->id,
            'user_id' => $agencyUser->id,
        ])->attachments()->create([
            'file_name' => 'passport.jpg',
            'file_path' => 'client-request-attachments/'.$clientRequest->id.'/passport.jpg',
            'file_type' => 'image/jpeg',
            'size' => 204800,
        ]);

        $this->actingAs($agencyUser)
            ->get(route('referrals.show', $referral))
            ->assertInertia(fn ($page) => $page
                ->where('clientRequestPermissions', [
                    'canCreate' => true,
                    'canReply' => true,
                    'canTransition' => true,
                    'canRevokeAccess' => true,
                ])
                ->where('clientRequestHistory.0.id', $clientRequest->id)
                ->where('clientRequestHistory.0.referral_id', $referral->id)
                ->where('clientRequestHistory.0.access_links.0.id', $link->id)
                ->missing('clientRequestHistory.0.access_links.0.token_hash')
                ->missing('clientRequestHistory.0.access_links.0.recipient_snapshot')
                ->where('clientRequestHistory.0.messages.0.user.id', $agencyUser->id)
                ->where('clientRequestHistory.0.messages.0.attachments.0.file_name', 'passport.jpg')
                ->where('clientRequestHistory.0.messages.0.attachments.0.file_type', 'image/jpeg')
                ->where('clientRequestHistory.0.messages.0.attachments.0.size', 204800)
                ->missing('clientRequestHistory.0.messages.0.attachments.0.file_path'));

        $this->actingAs($owner)
            ->get(route('referrals.show', $referral))
            ->assertInertia(fn ($page) => $page->where('clientRequestPermissions', [
                'canCreate' => false,
                'canReply' => false,
                'canTransition' => false,
                'canRevokeAccess' => true,
            ]));
    }

    public function test_show_withholds_client_request_write_permissions_when_case_is_closed(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->closed()->create(['user_id' => $owner->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $this->actingAs($agencyUser)
            ->get(route('referrals.show', $referral))
            ->assertInertia(fn ($page) => $page->where('clientRequestPermissions', [
                'canCreate' => false,
                'canReply' => false,
                'canTransition' => false,
                'canRevokeAccess' => true,
            ]));
    }

    public function test_show_withholds_client_request_write_permissions_when_referral_is_completed(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create(['user_id' => $owner->id]);
        $referral = Referral::factory()->completed()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $this->actingAs($agencyUser)
            ->get(route('referrals.show', $referral))
            ->assertInertia(fn ($page) => $page->where('clientRequestPermissions', [
                'canCreate' => false,
                'canReply' => false,
                'canTransition' => false,
                'canRevokeAccess' => true,
            ]));
    }

    public function test_store_rejects_client_request_on_closed_case_with_form_error(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->closed()->create(['user_id' => $owner->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $this->actingAs($agencyUser)
            ->post(route('referrals.client-requests.store', $referral), [
                'type' => 'DOCUMENT_REQUEST',
                'title' => 'Please submit your valid ID',
                'instructions' => 'We need a clear copy of your government-issued ID.',
                'checklist' => ['Valid ID'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('referral_client_requests', ['referral_id' => $referral->id]);
    }

    public function test_index_redirects_browser_navigation_to_referral_page(): void
    {
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $this->actingAs($agencyUser)
            ->get(route('referrals.client-requests.index', $referral))
            ->assertRedirect(route('referrals.show', $referral));

        $this->actingAs($agencyUser)
            ->getJson(route('referrals.client-requests.index', $referral))
            ->assertOk()
            ->assertJson(['data' => []]);
    }
}
