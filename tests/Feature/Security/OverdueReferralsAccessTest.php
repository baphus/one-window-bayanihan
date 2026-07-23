<?php

namespace Tests\Feature\Security;

use App\Mail\ReferralOverdueMail;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OverdueReferralsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Create a referral with specific timestamps, bypassing $fillable protection
     * for created_at/updated_at.
     */
    private function createReferralWithAge(array $overrides = [], int $daysAgo = 12): Referral
    {
        $referral = new Referral;
        $referral->timestamps = false;

        $referral->required_services = $overrides['required_services'] ?? 'Test service';
        $referral->status = $overrides['status'] ?? 'PENDING';
        $referral->case_id = $overrides['case_id'] ?? CaseFile::factory()->create()->id;
        $referral->agcy_id = $overrides['agcy_id'] ?? Agency::factory()->create()->id;

        $referral->created_at = now()->subDays($daysAgo);
        $referral->updated_at = now()->subDays($daysAgo);
        $referral->save();

        $referral->timestamps = true;

        return $referral;
    }

    #[Test]
    public function case_manager_can_access_overdue_referrals(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($cm)->get(route('overdue-referrals.index'));

        $response->assertOk();
    }

    #[Test]
    public function admin_can_access_overdue_referrals(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response->assertOk();
    }

    #[Test]
    public function agency_can_access_overdue_referrals_page(): void
    {
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => Agency::factory()]);

        $response = $this->actingAs($agencyUser)->get(route('overdue-referrals.index'));

        $response->assertOk();
    }

    #[Test]
    public function page_has_correct_inertia_props(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/OverdueReferrals/Index')
                ->has('stats')
                ->has('referrals')
                ->has('userRole')
                ->has('overdueDays')
                ->where('userRole', 'ADMIN')
            );
    }

    #[Test]
    public function admin_sees_all_agencies_overdue_referrals(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency1 = Agency::factory()->create();
        $agency2 = Agency::factory()->create();

        $this->createReferralWithAge(['agcy_id' => $agency1->id], daysAgo: 12);
        $this->createReferralWithAge(['agcy_id' => $agency2->id], daysAgo: 12);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 2)
            );
    }

    #[Test]
    public function case_manager_sees_only_their_cases_overdue_referrals(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherCm = User::factory()->create(['role' => 'CASE_MANAGER']);

        $cmCase = CaseFile::factory()->create(['user_id' => $cm->id]);
        $otherCase = CaseFile::factory()->create(['user_id' => $otherCm->id]);

        $ownReferral = $this->createReferralWithAge(['case_id' => $cmCase->id], daysAgo: 12);
        $this->createReferralWithAge(['case_id' => $otherCase->id], daysAgo: 12);

        $response = $this->actingAs($cm)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 1)
                ->where('referrals.data.0.id', $ownReferral->id)
            );
    }

    #[Test]
    public function agency_sees_only_referrals_to_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $agencyUser = User::factory()->create([
            'role' => 'AGENCY',
            'agcy_id' => $agency->id,
        ]);

        $ownReferral = $this->createReferralWithAge(['agcy_id' => $agency->id], daysAgo: 12);
        $this->createReferralWithAge(['agcy_id' => $otherAgency->id], daysAgo: 12);

        $response = $this->actingAs($agencyUser)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 1)
                ->where('referrals.data.0.id', $ownReferral->id)
            );
    }

    #[Test]
    public function inactivity_based_overdue_excludes_referrals_with_recent_activity(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency = Agency::factory()->create();
        $caseFile = CaseFile::factory()->create();

        // Stale referral — no milestones
        $staleReferral = $this->createReferralWithAge([
            'agcy_id' => $agency->id,
            'case_id' => $caseFile->id,
        ], daysAgo: 30);

        // Active referral — old but has a recent milestone
        $activeReferral = $this->createReferralWithAge([
            'agcy_id' => $agency->id,
            'case_id' => $caseFile->id,
        ], daysAgo: 30);

        Milestone::factory()->create([
            'refr_id' => $activeReferral->id,
            'created_at' => now()->subDays(2), // recent activity — not overdue
        ]);

        $response = $this->actingAs($admin)->get(route('overdue-referrals.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('referrals.data', 1)
                ->where('referrals.data.0.id', $staleReferral->id)
            );
    }

    #[Test]
    public function case_manager_cannot_send_a_reminder_for_another_managers_referral(): void
    {
        Mail::fake();
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherCm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agency = Agency::factory()->create();
        User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        $ownCase = CaseFile::factory()->create(['user_id' => $cm->id]);
        $otherCase = CaseFile::factory()->create(['user_id' => $otherCm->id]);
        $own = $this->createReferralWithAge(['case_id' => $ownCase->id, 'agcy_id' => $agency->id], 12);
        $other = $this->createReferralWithAge(['case_id' => $otherCase->id, 'agcy_id' => $agency->id], 12);

        $this->actingAs($cm)->post(route('overdue-referrals.send-reminders'), ['referral_ids' => [$other->id]])->assertRedirect();
        Mail::assertQueued(ReferralOverdueMail::class, 0);

        $this->actingAs($cm)->post(route('overdue-referrals.send-reminders'), ['referral_ids' => [$own->id]])->assertRedirect();
        Mail::assertQueued(ReferralOverdueMail::class, 1);
        Mail::assertQueued(ReferralOverdueMail::class, fn (ReferralOverdueMail $mail) => $mail->referral->is($own));
    }

    #[Test]
    public function empty_reminder_selection_targets_the_same_scoped_inactive_set_as_display(): void
    {
        Mail::fake();
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $otherCm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agency = Agency::factory()->create();
        User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $ownCase = CaseFile::factory()->create(['user_id' => $cm->id]);
        $otherCase = CaseFile::factory()->create(['user_id' => $otherCm->id]);
        $this->createReferralWithAge(['case_id' => $ownCase->id, 'agcy_id' => $agency->id], 12);
        $recentlyMilestoned = $this->createReferralWithAge(['case_id' => $ownCase->id, 'agcy_id' => $agency->id], 30);
        Milestone::factory()->create([
            'refr_id' => $recentlyMilestoned->id,
            'created_at' => now()->subDays(2),
        ]);
        $other = $this->createReferralWithAge(['case_id' => $otherCase->id, 'agcy_id' => $agency->id], 12);

        $this->actingAs($cm)->post(route('overdue-referrals.send-reminders'), ['referral_ids' => []])->assertRedirect();

        Mail::assertQueued(ReferralOverdueMail::class, 1);
        Mail::assertQueued(ReferralOverdueMail::class, fn (ReferralOverdueMail $mail) => $mail->referral->id !== $other->id);
        Mail::assertQueued(ReferralOverdueMail::class, fn (ReferralOverdueMail $mail) => $mail->referral->id !== $recentlyMilestoned->id);
    }

    #[Test]
    public function stats_cover_the_full_overdue_query_when_results_are_paginated(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency = Agency::factory()->create();
        $caseFile = CaseFile::factory()->create();

        foreach (range(1, 16) as $unused) {
            $this->createReferralWithAge(['case_id' => $caseFile->id, 'agcy_id' => $agency->id], 12);
        }

        $this->actingAs($admin)->get(route('overdue-referrals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 16)
                ->has('referrals.data', 15)
                ->where('referrals.total', 16)
            );
    }

    #[Test]
    public function filtered_bulk_reminders_send_only_matching_status(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $agency = Agency::factory()->create();
        User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $caseFile = CaseFile::factory()->create();
        $this->createReferralWithAge(['case_id' => $caseFile->id, 'agcy_id' => $agency->id, 'status' => 'PENDING'], 12);
        $processing = $this->createReferralWithAge(['case_id' => $caseFile->id, 'agcy_id' => $agency->id, 'status' => 'PROCESSING'], 12);

        $this->actingAs($admin)->post(route('overdue-referrals.send-reminders'), [
            'status_filter' => 'pending',
        ])->assertRedirect();

        Mail::assertQueued(ReferralOverdueMail::class, 1);
        Mail::assertQueued(ReferralOverdueMail::class, fn (ReferralOverdueMail $mail) => $mail->referral->id !== $processing->id);
    }

    #[Test]
    public function agency_cannot_post_overdue_reminders(): void
    {
        Mail::fake();
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);

        $this->actingAs($agencyUser)
            ->post(route('overdue-referrals.send-reminders'))
            ->assertForbidden();

        Mail::assertNothingQueued();
    }

    #[Test]
    public function malformed_reminder_ids_fail_validation_instead_of_querying(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)
            ->post(route('overdue-referrals.send-reminders'), ['referral_ids' => ['not-a-uuid']])
            ->assertSessionHasErrors('referral_ids.0');

        Mail::assertNothingQueued();
    }
}
