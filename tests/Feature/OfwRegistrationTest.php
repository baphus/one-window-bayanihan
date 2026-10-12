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
        $response = $this->get(route('register'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_guest_can_start_two_step_registration(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('register.profile'));
        $this->assertSessionHas('pending_registration');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'juan@example.com')->first());
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'juan@example.com', 'role' => UserRole::OFW->value]);

        $response = $this->post(route('register.store'), [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_requires_valid_password(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_authenticated_user_cannot_access_registration(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAs($user);

        $response = $this->get(route('register'));
        $response->assertRedirect();
    }

    public function test_ofw_can_access_registration_profile_step(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAs($user);

        $response = $this->get(route('register.profile'));
        $response->assertRedirect();
    }
}
