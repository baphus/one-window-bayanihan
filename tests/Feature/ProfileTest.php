<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsWithMfa($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsWithMfa($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsWithMfa($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_update_cannot_bypass_email_change_verification(): void
    {
        $user = User::factory()->create();
        $originalEmail = $user->email;

        $this->actingAsWithMfa($user)->from('/profile')->patch('/profile', [
            'name' => 'Changed Name',
            'email' => 'changed@example.com',
        ])->assertRedirect('/profile')->assertSessionHasErrors('email');

        $this->assertSame($originalEmail, $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNotSame('Changed Name', $user->fresh()->name);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAsWithMfa($user)->delete('/profile', [
            'password' => 'P@ssw0rd!',
        ])->assertForbidden();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->fresh()->is_deleted);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsWithMfa($user)
            ->delete('/profile', [
                'password' => 'P@ssw0rd!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_deleted' => 1,
        ]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsWithMfa($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
