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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

    private function validIntakeData(?string $categoryId = null): array
    {
        if (! $categoryId) {
            $category = CaseCategory::create([
                'id' => Str::uuid()->toString(),
                'name' => 'Labor Dispute',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $categoryId = $category->id;
        }

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
            'category_ids' => [$categoryId],
            'vulnerability_indicator' => null,
            'summary' => 'I need help with unpaid wages from my employer for the past 3 months.',
            'consent' => true,
            'password' => 'SecureP@ss123',
            'password_confirmation' => 'SecureP@ss123',
        ];
    }

    #[Test]
    public function test_intake_creates_client_user_and_case(): void
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

        // Assert OFW user was created
        $user = User::where('email', $email)->where('role', 'OFW')->first();
        $this->assertNotNull($user, 'OFW user should have been created');
        $this->assertEquals($client->id, $user->client_id);

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
}
