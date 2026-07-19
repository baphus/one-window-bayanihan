<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\ReferralClientRequest;
use App\Models\User;
use App\Services\ReferralClientAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ReferralClientInboxTestCase extends TestCase
{
    use RefreshDatabase;

    protected function context(bool $withEmail = true): array
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id, 'is_active' => true]);
        $otherAgencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $otherAgency->id, 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'CASE_MANAGER', 'is_active' => true]);
        $client = Client::factory()->create(['email' => $withEmail ? fake()->safeEmail() : null]);
        $case = CaseFile::factory()->create(['client_id' => $client->id, 'user_id' => $manager->id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        $clientRequest = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);

        return compact('agency', 'otherAgency', 'agencyUser', 'otherAgencyUser', 'manager', 'client', 'case', 'referral', 'clientRequest');
    }

    protected function issue(array $context, ?ReferralClientRequest $request = null): array
    {
        return app(ReferralClientAccessService::class)->issue(
            $request ?? $context['clientRequest'],
            $context['agencyUser'],
            ['email' => $context['client']->email, 'name' => 'Test Client'],
        );
    }
}
