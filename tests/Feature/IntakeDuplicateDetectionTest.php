<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeDuplicateDetectionTest extends TestCase
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
        Mail::fake();
    }

    #[Test]
    public function test_duplicate_detected_when_active_case_exists(): void
    {
        $email = 'existing-ofw@example.com';

        $client = Client::factory()->create(['email' => $email]);
        $ofwUser = User::factory()->create([
            'email' => $email,
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        // Set up OTP in cache so verification succeeds
        Cache::put("otp:intake:{$email}", '123456', 300);

        $response = $this->postJson('/intake/check-duplicate', [
            'email' => $email,
            'otp' => '123456',
        ]);

        $response->assertOk();
        $response->assertJson([
            'verified' => true,
            'duplicate' => true,
        ]);
    }

    #[Test]
    public function test_no_duplicate_when_only_closed_cases(): void
    {
        $email = 'closed-ofw@example.com';

        $client = Client::factory()->create(['email' => $email]);
        $ofwUser = User::factory()->create([
            'email' => $email,
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        CaseFile::factory()->closed()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        Cache::put("otp:intake:{$email}", '123456', 300);

        $response = $this->postJson('/intake/check-duplicate', [
            'email' => $email,
            'otp' => '123456',
        ]);

        $response->assertOk();
        $response->assertJson([
            'verified' => true,
            'duplicate' => false,
        ]);
    }

    #[Test]
    public function test_no_duplicate_for_new_email(): void
    {
        $email = 'brand-new-ofw@example.com';

        Cache::put("otp:intake:{$email}", '123456', 300);

        $response = $this->postJson('/intake/check-duplicate', [
            'email' => $email,
            'otp' => '123456',
        ]);

        $response->assertOk();
        $response->assertJson([
            'verified' => true,
            'duplicate' => false,
            'existing_client' => null,
        ]);
    }
}
