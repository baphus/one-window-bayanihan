<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deleting_an_account_keeps_the_row_and_revokes_request_links(): void
    {
        $client = User::factory()->create([
            'role' => UserRole::OFW->value,
            'password' => Hash::make('P@ssw0rd!'),
        ]);
        $link = $this->linkIssuedBy($client);

        $this->actingAs($client)
            ->delete(route('profile.destroy'), ['password' => 'P@ssw0rd!'])
            ->assertRedirect('/');

        $this->assertSoftDeleted('users', ['id' => $client->id]);
        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertNull(ReferralClientAccessLink::query()->usable()->find($link->id));
    }

    /**
     * The account row stays because other tables point at it. A reply the
     * owner sent before deleting must still render with the sender named as
     * the client, not vanish from the agency's inbox.
     */
    #[Test]
    public function owner_replies_survive_the_deletion_and_cannot_be_forged_by_it(): void
    {
        $client = User::factory()->create([
            'role' => UserRole::OFW->value,
            'password' => Hash::make('P@ssw0rd!'),
        ]);
        $link = $this->linkIssuedBy($client);
        $message = ReferralClientMessage::create([
            'request_id' => $link->request_id,
            'body' => 'Uploaded my passport',
            'sender_kind' => ReferralClientMessage::SENDER_CLIENT_USER,
            'user_id' => $client->id,
            'kind' => ReferralClientMessage::KIND_MESSAGE,
        ]);

        $this->actingAs($client)
            ->delete(route('profile.destroy'), ['password' => 'P@ssw0rd!'])
            ->assertRedirect('/');

        $this->assertSame($client->id, $message->fresh()->user_id);
        $this->assertSame(ReferralClientMessage::SENDER_CLIENT_USER, $message->fresh()->sender_kind);
    }

    private function linkIssuedBy(User $issuer): ReferralClientAccessLink
    {
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $issuer->client_id]);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);
        $request = ReferralClientRequest::factory()->create(['referral_id' => $referral->id]);

        return ReferralClientAccessLink::create([
            'request_id' => $request->id,
            'token_hash' => hash('sha256', 'token-'.$issuer->id),
            'expires_at' => now()->addDays(7),
            'issued_by' => $issuer->id,
            'recipient_snapshot' => ['email' => 'client@example.com', 'name' => 'Client'],
        ]);
    }
}
