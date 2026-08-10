<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\MfaPendingState;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeSubmissionTest extends TestCase
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

    private function validIntakeData(): array
    {
        return [
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_initial' => 'M',
                'suffix' => null,
                'date_of_birth' => '1990-01-15',
                'sex' => 'MALE',
                'contact_number' => '+639171234567',
            ],
            'address' => [
                'region' => 'Region VII',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
                'street' => '123 Main Street',
            ],
            'employment' => [
                'employer_name' => 'Gulf Construction Co.',
                'position' => 'Welder',
                'country' => 'Saudi Arabia',
                'start_date' => '2020-03-01',
                'end_date' => null,
                'is_present' => true,
            ],
            'next_of_kin' => [
                [
                    'first_name' => 'Maria',
                    'last_name' => 'Dela Cruz',
                    'relationship' => 'Spouse',
                    'phone_number' => '+639181234567',
                ],
            ],
            'summary' => 'I need help with unpaid wages from my employer for the past 3 months.',
            'consent' => true,
        ];
    }

    #[Test]
    public function test_intake_creates_client_and_case(): void
    {
        $email = 'test@example.com';

        $response = $this->withSession(['intake_verified_email' => $email])
            ->postJson('/intake/submit', $this->validIntakeData());

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Assert client was created
        $client = Client::where('is_deleted', false)->get()->first(function (Client $c) use ($email) {
            return strtolower(trim($c->email)) === $email;
        });
        $this->assertNotNull($client, 'Client should have been created');
        $this->assertEquals('Juan', $client->first_name);
        $this->assertEquals('Dela Cruz', $client->last_name);

        // Assert no User account was created (accountless flow)
        $user = User::where('email', $email)->where('role', 'OFW')->first();
        $this->assertNull($user, 'OFW user should NOT be created in accountless intake');

        // Assert case was created
        $case = CaseFile::where('client_id', $client->id)
            ->where('source', CaseFile::SOURCE_SELF_FILED)
            ->first();
        $this->assertNotNull($case, 'Case should have been created');
        $this->assertEquals('DRAFT', $case->status);
        $this->assertEquals(CaseFile::SOURCE_SELF_FILED, $case->source);
    }

    #[Test]
    public function test_intake_requires_email_verification(): void
    {
        $response = $this->postJson('/intake/submit', $this->validIntakeData());

        $response->assertStatus(422);
    }

    #[Test]
    public function test_signed_in_ofw_can_submit_without_otp(): void
    {
        $email = 'juan.signedin@example.com';

        $user = User::factory()->mfaEnabled()->create([
            'role' => 'OFW',
            'email' => $email,
        ]);
        $client = Client::factory()->create(['email' => $email]);
        $user->client_id = $client->id;
        $user->save();

        // No intake_verified_email session flag — the authenticated OFW's
        // session is the verification.
        $response = $this->actingAs($user)
            ->postJson('/intake/submit', $this->validIntakeData());

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $case = CaseFile::where('client_id', $client->id)
            ->where('source', CaseFile::SOURCE_SELF_FILED)
            ->first();
        $this->assertNotNull($case, 'Case should have been created and linked to the signed-in client');
        $this->assertEquals('DRAFT', $case->status);
    }

    #[Test]
    public function test_signed_in_non_ofw_still_requires_otp(): void
    {
        $manager = User::factory()->mfaEnabled()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($manager)
            ->withSession([
                MfaPendingState::MARKER_KEY => [
                    'user_id' => $manager->id,
                    'credential_fingerprint' => hash('sha256', (string) $manager->password),
                ],
            ])
            ->postJson('/intake/submit', $this->validIntakeData());

        $response->assertStatus(422);
    }

    #[Test]
    public function test_intake_index_skips_verification_for_signed_in_ofw(): void
    {
        $email = 'juan.signedin@example.com';

        $user = User::factory()->mfaEnabled()->create([
            'role' => 'OFW',
            'email' => $email,
        ]);
        $client = Client::factory()->create(['email' => $email]);
        $user->client_id = $client->id;
        $user->save();

        $response = $this->actingAs($user)->get('/intake');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Intake/Index')
            ->where('skipVerification', true)
            ->where('existingClient.email', $email));
    }

    #[Test]
    public function test_intake_index_does_not_skip_for_anonymous_visitors(): void
    {
        $response = $this->get('/intake');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Intake/Index')
            ->where('skipVerification', false));
    }
}
