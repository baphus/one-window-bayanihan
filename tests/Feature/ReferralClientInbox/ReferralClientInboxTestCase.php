<?php

namespace Tests\Feature\ReferralClientInbox;

use App\Enums\UserRole;
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
        // uniqid()-based emails: faker's unique() pool still collides at
        // full-suite scale (users_email_unique violation), these cannot.
        $agencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $agency->id, 'is_active' => true, 'email' => 'agency-'.uniqid().'@example.com']);
        $otherAgencyUser = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $otherAgency->id, 'is_active' => true, 'email' => 'other-agency-'.uniqid().'@example.com']);
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value, 'is_active' => true, 'email' => 'manager-'.uniqid().'@example.com']);
        $client = Client::factory()->create(['email' => $withEmail ? 'client-'.uniqid().'@example.com' : null]);
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
