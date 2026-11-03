<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralClientAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_client_photo_prefers_account_avatar_then_falls_back_to_client_photo(): void
    {
        $account = User::factory()->create(['role' => UserRole::OFW->value, 'avatar_url' => 'https://example.test/account.jpg']);
        $client = Client::factory()->create(['user_id' => $account->id, 'avatar_url' => 'https://example.test/client.jpg']);

        $loadedClient = $this->loadReferralClient($client);

        $this->assertSame('https://example.test/account.jpg', $loadedClient->user->avatar_url ?: $loadedClient->avatar_url);
        $this->assertSame('https://example.test/client.jpg', $loadedClient->avatar_url);

        $account->update(['avatar_url' => null]);
        $loadedClient = $this->loadReferralClient($client);

        $this->assertSame('https://example.test/client.jpg', $loadedClient->user->avatar_url ?: $loadedClient->avatar_url);

        $client->update(['avatar_url' => null]);
        $loadedClient = $this->loadReferralClient($client);

        $this->assertNull($loadedClient->user->avatar_url ?: $loadedClient->avatar_url);
    }

    private function loadReferralClient(Client $client): Client
    {
        $case = CaseFile::factory()->create(['client_id' => $client->id, 'status' => 'OPEN']);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => Agency::factory()->create()->id]);

        return app(ReferralService::class)->getReferral($referral->id)->caseFile->client;
    }
}
