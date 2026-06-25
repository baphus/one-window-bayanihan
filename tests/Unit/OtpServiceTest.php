<?php

namespace Tests\Unit;

use App\Mail\OtpMail;
use App\Services\OtpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private OtpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OtpService::class);
        Mail::fake();
    }

    public function test_generate_returns_six_digit_string(): void
    {
        $otp = $this->service->generate('test@example.com');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
    }

    public function test_generate_stores_in_cache(): void
    {
        $otp = $this->service->generate('test@example.com');

        $this->assertTrue(Cache::has('otp:default:test@example.com'));
        $this->assertSame($otp, Cache::get('otp:default:test@example.com'));
    }

    public function test_verify_success_with_correct_code(): void
    {
        $otp = $this->service->generate('test@example.com');

        $result = $this->service->verify('test@example.com', 'default', $otp);

        $this->assertTrue($result);
    }

    public function test_verify_fails_with_wrong_code(): void
    {
        $this->service->generate('test@example.com');

        $result = $this->service->verify('test@example.com', 'default', '000000');

        $this->assertFalse($result);
    }

    public function test_verify_fails_after_max_attempts(): void
    {
        $this->service->generate('test@example.com');

        for ($i = 0; $i < 6; $i++) {
            $result = $this->service->verify('test@example.com', 'default', '000000');
            $this->assertFalse($result);
        }
    }

    public function test_max_attempts_invalidates_otp(): void
    {
        $otp = $this->service->generate('test@example.com');

        // Exhaust all 5 attempts with wrong codes
        for ($i = 0; $i < 5; $i++) {
            $this->service->verify('test@example.com', 'default', '000000');
        }

        // The correct OTP should now fail (invalidated after max attempts)
        $result = $this->service->verify('test@example.com', 'default', $otp);

        $this->assertFalse($result);
        $this->assertFalse(Cache::has('otp:default:test@example.com'));
    }

    public function test_attempt_counter_reset_on_new_generation(): void
    {
        $firstOtp = $this->service->generate('test@example.com');

        // Fail 3 times with wrong code
        for ($i = 0; $i < 3; $i++) {
            $this->service->verify('test@example.com', 'default', '000000');
        }

        // Generate a new OTP (this resets the attempts counter)
        $secondOtp = $this->service->generate('test@example.com');

        // Fail once with wrong code (counter should be 1, not 4)
        $this->service->verify('test@example.com', 'default', '000000');

        // Verify with the new correct OTP should succeed (1 < MAX_ATTEMPTS)
        $result = $this->service->verify('test@example.com', 'default', $secondOtp);

        $this->assertTrue($result);
    }

    public function test_sends_email_for_email_identifier(): void
    {
        $this->service->generate('test@example.com');

        Mail::assertQueued(OtpMail::class, function (OtpMail $mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_does_not_send_email_for_non_email_identifier(): void
    {
        $this->service->generate('09171234567');

        Mail::assertNothingQueued();
    }
}
