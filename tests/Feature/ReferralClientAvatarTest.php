<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralClientAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_uses_case_owned_identity_without_loading_legacy_client(): void
    {
        $account = User::factory()->create(['role' => UserRole::OFW->value, 'avatar_url' => 'https://example.test/account.jpg']);
        $case = CaseFile::factory()->withIntake()->create([
            'ofw_user_id' => $account->id,
            'first_name' => 'Case',
            'last_name' => 'Owned',
            'status' => 'OPEN',
        ]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => Agency::factory()->create()->id]);

        $loaded = app(ReferralService::class)->getReferral($referral->id)->caseFile;

        $this->assertSame('Case', $loaded->first_name);
        $this->assertSame('Owned', $loaded->last_name);
        $this->assertSame('https://example.test/account.jpg', $loaded->ofwUser->avatar_url);
        $this->assertFalse($loaded->relationLoaded('client'));
    }
}
