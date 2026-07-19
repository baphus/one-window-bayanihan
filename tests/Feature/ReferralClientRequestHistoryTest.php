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
                ->where('clientRequestHistory.0.access_links.0.id', $link->id)
                ->missing('clientRequestHistory.0.access_links.0.token_hash')
                ->missing('clientRequestHistory.0.access_links.0.recipient_snapshot')
                ->where('clientRequestHistory.0.messages.0.user.id', $agencyUser->id));

        $this->actingAs($owner)
            ->get(route('referrals.show', $referral))
            ->assertInertia(fn ($page) => $page->where('clientRequestPermissions', [
                'canCreate' => false,
                'canReply' => false,
                'canTransition' => false,
                'canRevokeAccess' => true,
            ]));
    }
}
