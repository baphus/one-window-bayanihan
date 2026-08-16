<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeRegistrationTest extends TestCase
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
    public function test_creates_ofw_account_linked_to_intake_client_and_logs_in(): void
    {
        $email = 'ofw-intake@example.com';

        $client = Client::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $email,
        ]);

        $response = $this->withSession(['intake_verified_email' => $email])
            ->postJson(route('intake.register'), [
                'password' => 'Str0ngPass!123',
                'password_confirmation' => 'Str0ngPass!123',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect' => route('ofw.dashboard'),
        ]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('OFW', $user->role);
        $this->assertEquals($client->id, $user->client_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue(Hash::check('Str0ngPass!123', $user->password));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function test_rejects_mismatched_password_confirmation(): void
    {
        $email = 'mismatch-intake@example.com';

        Client::factory()->create(['email' => $email]);

        $response = $this->withSession(['intake_verified_email' => $email])
            ->postJson(route('intake.register'), [
                'password' => 'Str0ngPass!123',
                'password_confirmation' => 'DifferentPass!123',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');

        $this->assertGuest();
        $this->assertEquals(0, User::where('email', $email)->count());
    }

    #[Test]
    public function test_requires_verified_email_session(): void
    {
        $response = $this->postJson(route('intake.register'), [
            'password' => 'Str0ngPass!123',
            'password_confirmation' => 'Str0ngPass!123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Session expired. Please complete the intake form again.',
        ]);

        $this->assertGuest();
    }
}
