<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
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

    public function test_guest_can_register_ofw_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertRedirect(route('ofw.dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role' => UserRole::OFW->value,
            'is_active' => true,
        ]);
    }

    public function test_registration_links_existing_client_by_email(): void
    {
        Client::factory()->create([
            'email' => 'juan@example.com',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);

        $this->post(route('register.store'), [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $user = User::where('email', 'juan@example.com')->first();
        $this->assertNotNull($user->client_id);
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

        $this->get(route('register'));
        $response = $this->get(route('register'));
        $response->assertRedirect();
    }

    public function test_unauthenticated_user_redirected_from_intake(): void
    {
        $response = $this->get(route('intake.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_ofw_can_access_intake(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->actingAs($user);

        $response = $this->get(route('intake.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Intake/Index'));
    }
}
