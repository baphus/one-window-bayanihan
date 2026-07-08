<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReferralDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $caseManager;

    private CaseFile $case;

    private Agency $agencyA;

    private Agency $agencyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $this->agencyA = Agency::factory()->create(['name' => 'Agency A']);
        $this->agencyB = Agency::factory()->create(['name' => 'Agency B']);

        $this->case = CaseFile::factory()->create([
            'user_id' => $this->caseManager->id,
            'status' => 'OPEN',
        ]);
    }

    #[Test]
    public function can_create_referrals_to_different_agencies(): void
    {
        // First referral to Agency A
        $response1 = $this->actingAs($this->caseManager)->post(route('referrals.store'), [
            'case_id' => $this->case->id,
            'agcy_id' => $this->agencyA->id,
            'services' => ['Test Service'],
            'notes' => 'First referral to Agency A',
        ]);
        $response1->assertRedirect();
        $this->assertEquals(1, Referral::where('case_id', $this->case->id)->count());

        // Second referral to Agency B (different agency)
        $response2 = $this->actingAs($this->caseManager)->post(route('referrals.store'), [
            'case_id' => $this->case->id,
            'agcy_id' => $this->agencyB->id,
            'services' => ['Another Service'],
            'notes' => 'Second referral to Agency B',
        ]);
        $response2->assertRedirect();
        $this->assertEquals(2, Referral::where('case_id', $this->case->id)->count());
    }

    #[Test]
    public function cannot_create_duplicate_referral_to_same_agency(): void
    {
        // Create first referral to Agency A
        $this->actingAs($this->caseManager)->post(route('referrals.store'), [
            'case_id' => $this->case->id,
            'agcy_id' => $this->agencyA->id,
            'services' => ['Test Service'],
            'notes' => 'First referral',
        ]);
        $this->assertEquals(1, Referral::where('case_id', $this->case->id)->count());

        // Try to create another referral to the SAME agency — should fail validation
        $response2 = $this->actingAs($this->caseManager)->post(route('referrals.store'), [
            'case_id' => $this->case->id,
            'agcy_id' => $this->agencyA->id,
            'services' => ['Another Service'],
            'notes' => 'Duplicate referral attempt',
        ]);

        // Should get validation error on agcy_id
        $response2->assertSessionHasErrors('agcy_id');
        // Ensure no second referral was created
        $this->assertEquals(1, Referral::where('case_id', $this->case->id)->count());
    }
}
