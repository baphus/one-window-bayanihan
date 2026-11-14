<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfwRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_registration_page(): void
    {
        $this->get(route('register'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Auth/Register')->where('initial.credentials.email', '')->missing('initial.profile'));
    }

    public function test_registration_requires_confirmed_strong_password(): void
    {
        $this->post(route('register.store'), [
            'email' => 'ofw@example.test', 'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');
        $this->post(route('register.store'), [
            'email' => 'ofw@example.test', 'password' => 'StrongPass123', 'password_confirmation' => 'Different123',
        ])->assertSessionHasErrors('password');
    }

    public function test_registration_rejects_public_profile_payload(): void
    {
        $this->post(route('register.store'), [
            'email' => 'ofw@example.test',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'first_name' => 'Juan',
            'type' => 'NEXT_OF_KIN',
        ])->assertSessionHasErrors(['first_name', 'type']);
        $this->assertNull(session('pending_registration'));
        $this->assertDatabaseMissing('users', ['email' => 'ofw@example.test']);
    }

    public function test_authenticated_user_cannot_access_registration(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAsWithMfa($user);
        $this->get(route('register'))->assertRedirect();
        $this->get(route('register.review'))->assertRedirect();
    }
}
