<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Mail\IntakeReceivedMail;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeReceivedMailTest extends TestCase
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
                'middle_name' => 'Mendoza',
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
    public function test_intake_submit_queues_received_mail_without_account(): void
    {
        $email = 'new-filer@example.com';

        $response = $this->withSession(['intake_verified_email' => $email])
            ->postJson('/intake/submit', $this->validIntakeData());

        $response->assertOk();
        $response->assertJson(['success' => true]);

        Mail::assertQueued(IntakeReceivedMail::class, function (IntakeReceivedMail $mail) use ($email) {
            return $mail->hasTo($email) && $mail->hasAccount === false;
        });
    }

    #[Test]
    public function test_intake_submit_queues_received_mail_with_account_flag(): void
    {
        $email = 'returning-ofw@example.com';

        User::factory()->mfaEnabled()->create([
            'role' => 'OFW',
            'email' => $email,
        ]);

        $response = $this->withSession(['intake_verified_email' => $email])
            ->postJson('/intake/submit', $this->validIntakeData());

        $response->assertOk();
        $response->assertJson(['success' => true]);

        Mail::assertQueued(IntakeReceivedMail::class, function (IntakeReceivedMail $mail) use ($email) {
            return $mail->hasTo($email) && $mail->hasAccount === true;
        });
    }
}
