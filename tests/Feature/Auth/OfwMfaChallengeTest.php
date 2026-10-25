<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfwMfaChallengeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function enrolled_ofw_is_challenged(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::OFW->value,
            'mfa_enabled_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('mfa.challenge.show'));
        $this->assertGuest();
        $this->assertNotNull(session('mfa_pending'));
    }

    #[Test]
    public function ofw_without_mfa_is_logged_in_directly(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::OFW->value,
            'password' => Hash::make('password'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('ofw.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function staff_with_mfa_enabled_is_challenged(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::CASE_MANAGER->value,
            'mfa_enabled_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('mfa.challenge.show'));
        $this->assertGuest();
        $this->assertNotNull(session('mfa_pending'));
    }
}
