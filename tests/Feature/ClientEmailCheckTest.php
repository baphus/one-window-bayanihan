<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * /api/clients/email-check — the duplicate-email probe the case-creation form
 * uses to offer "link this existing client" before entering the rest of the case.
 */
class ClientEmailCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function checkEmail(string $email): TestResponse
    {
        return $this->actingAs($this->user)->getJson(
            route('api.clients.email-check', ['email' => $email]),
        );
    }

    public function test_returns_duplicate_with_client_when_email_matches(): void
    {
        $existing = Client::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
            // The probe only matches published clients: linked account + live OPEN case.
            'user_id' => User::factory()->create()->id,
        ]);
        CaseFile::factory()->create([
            'client_id' => $existing->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'OPEN',
        ]);

        $response = $this->checkEmail('MARIA@Example.com ');

        $response->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('client.id', $existing->id)
            ->assertJsonPath('client.email', 'maria@example.com');
    }

    public function test_returns_no_duplicate_when_email_is_unknown(): void
    {
        $response = $this->checkEmail('nobody@example.com');

        $response->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('client', null);
    }

    public function test_returns_no_duplicate_for_empty_email(): void
    {
        $response = $this->checkEmail('');

        $response->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('client', null);
    }

    public function test_hides_client_whose_only_case_is_pending_review(): void
    {
        $client = Client::factory()->create(['email' => 'pending@example.com']);
        CaseFile::factory()->pendingReview()->create([
            'client_id' => $client->id,
            'user_id' => null,
        ]);

        $response = $this->checkEmail('pending@example.com');

        $response->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('client', null);
    }
}
