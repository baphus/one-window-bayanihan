<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\MfaPendingState;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();
        $this->withSession([MfaPendingState::MARKER_KEY => [
            'user_id' => (string) $user->id,
            'credential_fingerprint' => hash('sha256', (string) $user->password),
        ]]);

        $response = $this->actingAsWithMfa($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $this->withSession([MfaPendingState::MARKER_KEY => [
            'user_id' => (string) $user->id,
            'credential_fingerprint' => hash('sha256', (string) $user->password),
        ]]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAsWithMfa($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();
        $this->withSession([MfaPendingState::MARKER_KEY => [
            'user_id' => (string) $user->id,
            'credential_fingerprint' => hash('sha256', (string) $user->password),
        ]]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAsWithMfa($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
