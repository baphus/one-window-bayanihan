<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\MfaPendingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralMessageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Case with three agencies working on it (X, Y, Z), each holding a referral,
     * plus a case manager and an admin.
     *
     * @return array{case: CaseFile, agencyX: Agency, agencyY: Agency, agencyZ: Agency, xUser: User, yUser: User, zUser: User, owner: User, admin: User, referralX: Referral, referralY: Referral, referralZ: Referral}
     */
    private function threadFixtures(): array
    {
        $agencyX = Agency::factory()->create();
        $agencyY = Agency::factory()->create();
        $agencyZ = Agency::factory()->create();
        $xUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agencyX->id, 'is_active' => true]);
        $yUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agencyY->id, 'is_active' => true]);
        $zUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agencyZ->id, 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'CASE_MANAGER']);
        $admin = User::factory()->mfaEnabled()->create(['role' => 'ADMIN']);
        $case = CaseFile::factory()->create(['user_id' => $owner->id]);

        $referralX = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyX->id]);
        $referralY = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyY->id]);
        $referralZ = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyZ->id]);

        return compact(
            'case', 'agencyX', 'agencyY', 'agencyZ',
            'xUser', 'yUser', 'zUser', 'owner', 'admin',
            'referralX', 'referralY', 'referralZ',
        );
    }

    public function test_agency_working_on_case_can_send_message_to_peer_card(): void
    {
        $f = $this->threadFixtures();

        $response = $this->actingAs($f['yUser'])
            ->postJson(route('referrals.messages.store', $f['referralX']), [
                'body' => 'We received the referral and are processing it.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.body', 'We received the referral and are processing it.')
            ->assertJsonPath('data.sender.id', $f['yUser']->id)
            ->assertJsonPath('data.sender.agency.id', $f['agencyY']->id);

        $this->assertDatabaseHas('referral_messages', [
            'referral_id' => $f['referralX']->id,
            'sender_user_id' => $f['yUser']->id,
        ]);
    }

    public function test_both_participants_see_the_merged_pair_thread_across_the_case(): void
    {
        $f = $this->threadFixtures();

        // Y messages X via X's card; X replies via Y's card.
        $this->actingAs($f['yUser'])->postJson(route('referrals.messages.store', $f['referralX']), ['body' => 'First update.']);
        $this->actingAs($f['xUser'])->postJson(route('referrals.messages.store', $f['referralY']), ['body' => 'Second update.']);

        // Y listing via X's card sees both messages (oldest first).
        $this->actingAs($f['yUser'])
            ->getJson(route('api.referrals.messages.index', $f['referralX']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'First update.')
            ->assertJsonPath('data.1.body', 'Second update.');

        // X listing via Y's card sees the same merged thread.
        $this->actingAs($f['xUser'])
            ->getJson(route('api.referrals.messages.index', $f['referralY']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_case_manager_cannot_access_agency_threads(): void
    {
        $f = $this->threadFixtures();

        $this->actingAs($f['owner'])
            ->getJson(route('api.referrals.messages.index', $f['referralX']))
            ->assertForbidden();

        $this->actingAs($f['owner'])
            ->postJson(route('referrals.messages.store', $f['referralX']), ['body' => 'Should be denied.'])
            ->assertForbidden();

        $this->actingAs($f['owner'])
            ->postJson(route('referrals.messages.read', $f['referralX']))
            ->assertForbidden();

        $this->assertDatabaseMissing('referral_messages', ['sender_user_id' => $f['owner']->id]);
    }

    /** Seed the post-MFA session marker so MFA-enforced roles (ADMIN) pass middleware. */
    private function withMfaMarker(User $user): void
    {
        $this->withSession([
            MfaPendingState::MARKER_KEY => [
                'user_id' => $user->id,
                'credential_fingerprint' => hash('sha256', (string) $user->password),
            ],
        ]);
    }

    public function test_admin_cannot_access_agency_threads(): void
    {
        $f = $this->threadFixtures();
        $this->withMfaMarker($f['admin']);

        $this->actingAs($f['admin'])
            ->getJson(route('api.referrals.messages.index', $f['referralX']))
            ->assertForbidden();

        $this->actingAs($f['admin'])
            ->postJson(route('referrals.messages.store', $f['referralX']), ['body' => 'Should be denied.'])
            ->assertForbidden();
    }

    public function test_agency_not_working_on_case_cannot_access_threads(): void
    {
        $f = $this->threadFixtures();
        $otherCase = CaseFile::factory()->create(['user_id' => $f['owner']->id]);
        $outAgency = Agency::factory()->create();
        $outUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $outAgency->id, 'is_active' => true]);
        Referral::factory()->create(['case_id' => $otherCase->id, 'agcy_id' => $outAgency->id]);

        $this->actingAs($outUser)
            ->getJson(route('api.referrals.messages.index', $f['referralX']))
            ->assertForbidden();

        $this->actingAs($outUser)
            ->postJson(route('referrals.messages.store', $f['referralX']), ['body' => 'Not allowed.'])
            ->assertForbidden();

        $this->assertDatabaseMissing('referral_messages', ['sender_user_id' => $outUser->id]);
    }

    public function test_agency_cannot_open_thread_via_own_agencys_referral_card(): void
    {
        $f = $this->threadFixtures();

        $this->actingAs($f['xUser'])
            ->getJson(route('api.referrals.messages.index', $f['referralX']))
            ->assertForbidden();

        $this->actingAs($f['xUser'])
            ->postJson(route('referrals.messages.store', $f['referralX']), ['body' => 'Self-thread.'])
            ->assertForbidden();
    }

    public function test_pair_isolation_other_agencies_messages_are_invisible(): void
    {
        $f = $this->threadFixtures();

        // Z messages X on X's card.
        $this->actingAs($f['zUser'])
            ->postJson(route('referrals.messages.store', $f['referralX']), ['body' => 'Confidential Z to X.']);

        // Y listing X's card sees no messages — the Z<->X message stays private.
        $this->actingAs($f['yUser'])
            ->getJson(route('api.referrals.messages.index', $f['referralX']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unread_tracks_each_peer_pair_independently(): void
    {
        $f = $this->threadFixtures();

        // Z posts to Y's card (pair Z<->Y); X posts to Y's card (pair X<->Y).
        $this->actingAs($f['zUser'])
            ->postJson(route('referrals.messages.store', $f['referralY']), ['body' => 'From Z.']);
        $this->actingAs($f['xUser'])
            ->postJson(route('referrals.messages.store', $f['referralY']), ['body' => 'From X.']);

        // Y opens their own agency's referral page: the related list is X's card
        // and Z's card. Each card badges only its own pair's unread messages.
        $this->actingAs($f['yUser'])
            ->get(route('referrals.show', $f['referralY']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relatedReferrals.0.agcy_id', $f['agencyX']->id)
                ->where('relatedReferrals.0.can_message', true)
                ->where('relatedReferrals.0.unread_count', 1)
                ->where('relatedReferrals.1.agcy_id', $f['agencyZ']->id)
                ->where('relatedReferrals.1.can_message', true)
                ->where('relatedReferrals.1.unread_count', 1));

        // Reading the X card clears only the X<->Y pair; the Z<->Y pair stays unread.
        $this->actingAs($f['yUser'])
            ->postJson(route('referrals.messages.read', $f['referralX']))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($f['yUser'])
            ->get(route('referrals.show', $f['referralY']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relatedReferrals.0.agcy_id', $f['agencyX']->id)
                ->where('relatedReferrals.0.unread_count', 0)
                ->where('relatedReferrals.1.agcy_id', $f['agencyZ']->id)
                ->where('relatedReferrals.1.unread_count', 1));
    }

    public function test_empty_body_is_rejected(): void
    {
        $f = $this->threadFixtures();

        $this->actingAs($f['yUser'])
            ->postJson(route('referrals.messages.store', $f['referralX']), ['body' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseMissing('referral_messages', ['referral_id' => $f['referralX']->id]);
    }

    public function test_index_redirects_browser_navigation_to_referral_page(): void
    {
        $f = $this->threadFixtures();

        $this->actingAs($f['yUser'])
            ->get(route('api.referrals.messages.index', $f['referralX']))
            ->assertRedirect(route('referrals.show', $f['referralX']));
    }

    public function test_case_manager_sees_no_messaging_affordance_or_unread_figures(): void
    {
        $f = $this->threadFixtures();

        $this->actingAs($f['zUser'])
            ->postJson(route('referrals.messages.store', $f['referralY']), ['body' => 'For the record.']);

        // For the case manager every related card has can_message=false and no unread
        // counts leak across the case.
        $this->actingAs($f['owner'])
            ->get(route('referrals.show', $f['referralX']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relatedReferrals.0.can_message', false)
                ->where('relatedReferrals.0.unread_count', 0)
                ->where('relatedReferrals.1.can_message', false)
                ->where('relatedReferrals.1.unread_count', 0));

        // An agency working on the case sees the affordance and its own pair's count.
        $this->actingAs($f['yUser'])
            ->get(route('referrals.show', $f['referralY']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relatedReferrals.1.agcy_id', $f['agencyZ']->id)
                ->where('relatedReferrals.1.can_message', true)
                ->where('relatedReferrals.1.unread_count', 1));
    }
}
