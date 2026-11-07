<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\CheckMfaEnrolled;
use App\Http\Middleware\EnsureMfaSession;
use App\Models\User;
use App\Services\MfaPendingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsWithMfa($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'P@ssw0rd!',
                'password' => 'New-P@ss1',
                'password_confirmation' => 'New-P@ss1',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('New-P@ss1', $user->refresh()->password));
    }

    public function test_password_update_keeps_enrolled_session_authenticated_and_clears_confirmation(): void
    {
        $user = User::factory()->mfaEnabled()->create();
        $this->actingAsWithMfa($user);
        $this->withSession([
            MfaPendingState::MARKER_KEY => [
                'user_id' => (string) $user->id,
                'credential_fingerprint' => hash('sha256', (string) $user->password),
            ],
        ]);
        $this->withMiddleware([
            CheckMfaEnrolled::class,
            EnsureMfaSession::class,
        ]);
        session([
            MfaPendingState::CONFIRMATION_KEY => ['user_id' => (string) $user->id, 'stale' => true],
        ]);

        $this->put('/password', [
            'current_password' => 'P@ssw0rd!',
            'password' => 'New-P@ss1',
            'password_confirmation' => 'New-P@ss1',
        ])->assertRedirect();

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $this->assertFalse(session()->has(MfaPendingState::CONFIRMATION_KEY));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsWithMfa($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'New-P@ss1',
                'password_confirmation' => 'New-P@ss1',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('P@ssw0rd!', $user->refresh()->password));
    }
}
