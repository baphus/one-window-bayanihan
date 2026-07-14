<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralCommentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAgencyUserWithReferral(): array
    {
        $case = CaseFile::factory()->create();
        $referral = Referral::factory()->create(['case_id' => $case->id]);
        $user = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $referral->agcy_id]);

        return [$user, $referral];
    }

    public function test_client_visible_comment_is_rejected(): void
    {
        [$user, $referral] = $this->makeAgencyUserWithReferral();

        $response = $this->actingAs($user)->post(route('referrals.comments.store', $referral->id), [
            'content' => 'A note meant for the client',
            'visibility' => 'CLIENT_VISIBLE',
        ]);

        $response->assertSessionHasErrors(['visibility']);
        $this->assertDatabaseMissing('referral_comments', ['visibility' => 'CLIENT_VISIBLE']);
    }

    public function test_client_visible_reply_is_rejected(): void
    {
        [$user, $referral] = $this->makeAgencyUserWithReferral();

        $comment = ReferralComment::create([
            'refr_id' => $referral->id,
            'content' => 'Parent comment',
            'visibility' => 'INTERNAL',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(
            route('referrals.comments.reply', [$referral->id, $comment->id]),
            ['content' => 'A reply meant for the client', 'visibility' => 'CLIENT_VISIBLE'],
        );

        $response->assertSessionHasErrors(['visibility']);
        $this->assertDatabaseMissing('referral_comments', ['visibility' => 'CLIENT_VISIBLE']);
    }

    public function test_internal_and_agency_only_visibilities_are_accepted(): void
    {
        [$user, $referral] = $this->makeAgencyUserWithReferral();

        foreach (['INTERNAL', 'AGY_ONLY'] as $visibility) {
            $response = $this->actingAs($user)->post(route('referrals.comments.store', $referral->id), [
                'content' => "A $visibility note",
                'visibility' => $visibility,
            ]);

            $response->assertSessionDoesntHaveErrors();
            $this->assertDatabaseHas('referral_comments', ['visibility' => $visibility]);
        }
    }
}
