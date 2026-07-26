<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeReturningOfwTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([
            VerifyTurnstile::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
        ]);
    }

    #[Test]
    public function test_returning_ofw_links_to_existing_client_without_password(): void
    {
        $email = 'returning-ofw@example.com';

        // Create existing client and OFW user with a CLOSED case
        $client = Client::factory()->create(['email' => $email]);
        $existingUser = User::factory()->create([
            'email' => $email,
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $originalPasswordHash = $existingUser->password;

        CaseFile::factory()->closed()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        // Create a real category
        $category = CaseCategory::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Contract Dispute',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $clientCountBefore = Client::where('is_deleted', false)->count();

        // No password provided — returning OFW keeps their existing password
        $data = [
            'client' => [
                'first_name' => 'Updated First',
                'last_name' => 'Updated Last',
                'middle_initial' => null,
                'suffix' => null,
                'date_of_birth' => '1985-06-15',
                'sex' => 'FEMALE',
                'contact_number' => '+639199999999',
            ],
            'next_of_kin' => [
                [
                    'first_name' => 'Emergency',
                    'last_name' => 'Contact',
                    'relationship' => 'Sibling',
                    'phone_number' => '+639181112222',
                ],
            ],
            'category_ids' => [$category->id],
            'summary' => 'Returning OFW with a new issue about contract violations at workplace.',
            'consent' => true,
        ];

        $response = $this->withSession(['intake_verified_email' => $email])
            ->postJson('/intake/submit', $data);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // No new client created
        $clientCountAfter = Client::where('is_deleted', false)->count();
        $this->assertEquals($clientCountBefore, $clientCountAfter);

        // User's client_id should still point to the same client
        $existingUser->refresh();
        $this->assertEquals($client->id, $existingUser->client_id);

        // Password should NOT have changed (no new password was provided)
        $this->assertEquals($originalPasswordHash, $existingUser->password);

        // New case should be linked to existing client
        $newCase = CaseFile::where('client_id', $client->id)
            ->where('source', CaseFile::SOURCE_SELF_FILED)
            ->where('status', 'DRAFT')
            ->latest()
            ->first();
        $this->assertNotNull($newCase);
    }

    #[Test]
    public function test_returning_ofw_can_update_password(): void
    {
        $email = 'returning-ofw-update-pw@example.com';

        $client = Client::factory()->create(['email' => $email]);
        $existingUser = User::factory()->create([
            'email' => $email,
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $oldHash = $existingUser->password;

        CaseFile::factory()->closed()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        $category = CaseCategory::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Contract Dispute',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Returning OFW provides a new password
        $data = [
            'client' => [
                'first_name' => 'Updated',
                'last_name' => 'User',
                'middle_initial' => null,
                'suffix' => null,
                'date_of_birth' => '1985-06-15',
                'sex' => 'FEMALE',
                'contact_number' => '+639199999999',
            ],
            'next_of_kin' => [
                [
                    'first_name' => 'Emergency',
                    'last_name' => 'Contact',
                    'relationship' => 'Sibling',
                    'phone_number' => '+639181112222',
                ],
            ],
            'category_ids' => [$category->id],
            'summary' => 'Returning OFW wants to update their password with the new submission.',
            'consent' => true,
            'password' => 'NewSecureP@ss1',
            'password_confirmation' => 'NewSecureP@ss1',
        ];

        $response = $this->withSession(['intake_verified_email' => $email])
            ->postJson('/intake/submit', $data);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $existingUser->refresh();
        $this->assertNotEquals($oldHash, $existingUser->password, 'Password should have been updated');
        $this->assertTrue(Hash::check('NewSecureP@ss1', $existingUser->password));
    }
}
