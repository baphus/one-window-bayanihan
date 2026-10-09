<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the OFW self-filing submission against rate-limit collisions.
 *
 * Laravel's unnamed `throttle:N,1` middleware resolves a guest's key as
 * sha1(domain|ip) — the route plays no part. Every public route declared with an
 * inline limit therefore shares ONE counter per visitor, and the tightest limit
 * among them caps the sum of all of them.
 *
 * (History: the intake wizard used to cascade through five public
 * /api/address/* lookups that shared the same inline budget, so every real
 * submission returned 429. The wizard now reads the bundled
 * philippine-addresses.ts instead, and the orphaned endpoints were removed —
 * but the named-limiter isolation below stays, guarding the remaining steps.)
 *
 * These tests keep ThrottleRequests enabled on purpose — the sibling
 * IntakeSubmissionTest disables it, which is exactly why the defect survived.
 */
class IntakeSubmitThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // Turnstile only: ThrottleRequests is the subject of this test.
        $this->withoutMiddleware([VerifyTurnstile::class]);
    }

    private function validIntakeData(): array
    {
        return [
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'sex' => 'MALE',
                'date_of_birth' => '1990-01-15',
                'contact_number' => '+639171234567',
            ],
            'address' => [
                'region' => 'Region VII',
                'province' => 'Cebu',
                'city_municipality' => 'Cebu City',
                'barangay' => 'Lahug',
                'street' => '123 Main Street',
            ],
            'next_of_kin' => [
                [
                    'first_name' => 'Maria',
                    'last_name' => 'Dela Cruz',
                    'relationship' => 'Spouse',
                ],
            ],
            'summary' => 'I need help with unpaid wages from my employer for the past 3 months.',
            'consent' => true,
        ];
    }

    #[Test]
    public function test_otp_and_duplicate_checks_do_not_consume_the_intake_submit_budget(): void
    {
        // Reaching the Submit step requires these; a filer who mistypes the code
        // a few times must still be able to submit.
        $this->postJson('/intake/verify-email', ['email' => 'throttle-probe@example.com'])->assertOk();

        foreach (range(1, 4) as $ignored) {
            $this->postJson('/intake/check-duplicate', [
                'email' => 'throttle-probe@example.com',
                'otp' => '000000',
            ])->assertStatus(422);
        }

        $response = $this->withSession(['intake_verified_email' => 'throttle-probe@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData());

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    #[Test]
    public function test_intake_submit_still_rate_limits_its_own_repeated_calls(): void
    {
        // The isolation above must not become "no limit at all". Without a
        // verified email each attempt is rejected at 422, but every attempt
        // still passes through the limiter.
        foreach (range(1, 5) as $ignored) {
            $this->postJson('/intake/submit', $this->validIntakeData())->assertStatus(422);
        }

        $this->postJson('/intake/submit', $this->validIntakeData())->assertStatus(429);
    }

    #[Test]
    public function test_csp_reports_do_not_consume_the_intake_submit_budget(): void
    {
        // The browser posts CSP violation reports from the intake page itself,
        // unprompted, so they spent the shared guest budget before the filer had
        // done anything at all.
        foreach (range(1, 6) as $ignored) {
            $this->postJson('/api/csp/report', ['csp-report' => ['document-uri' => 'https://example.test/intake']]);
        }

        $response = $this->withSession(['intake_verified_email' => 'throttle-probe@example.com'])
            ->postJson('/intake/submit', $this->validIntakeData());

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }
}
