<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_ofw_can_update_contact_but_staff_and_identity_fields_are_rejected(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::OFW->value,
            'position' => 'Existing staff title',
            'department' => 'Existing department',
            'office_location' => 'Existing office',
            'bio' => 'Existing bio',
            'emergency_contact' => ['name' => 'Existing contact', 'relation' => 'Sibling', 'phone' => '09170000000'],
            'notifications_config' => ['email_on_case_assigned' => true],
        ]);

        $this->actingAsWithMfa($user)->patch('/profile', [
            'name' => 'Updated OFW Name',
            'email' => $user->email,
            'timezone' => 'Asia/Manila',
            'position' => 'Attacker title',
            'department' => 'Attacker department',
            'office_location' => 'Attacker office',
            'bio' => 'Attacker bio',
            'emergency_contact' => ['name' => 'Attacker', 'relation' => 'Other', 'phone' => '09999999999'],
            'notifications_config' => ['email_on_case_assigned' => false],
        ])->assertSessionHasErrors(['email', 'timezone', 'position', 'department', 'office_location', 'bio', 'emergency_contact', 'notifications_config']);

        $this->actingAsWithMfa($user)->patch('/profile', ['contact_number' => '09181112222'])
            ->assertRedirect(route('ofw.profile.edit'));

        $user->refresh();
        $this->assertNotSame('Updated OFW Name', $user->name);
        $this->assertSame('09181112222', $user->contact_number);
        $this->assertSame('Asia/Manila', $user->timezone);
        $this->assertSame('Existing staff title', $user->position);
        $this->assertSame('Existing department', $user->department);
        $this->assertSame('Existing office', $user->office_location);
        $this->assertSame('Existing bio', $user->bio);
        $this->assertSame(['name' => 'Existing contact', 'relation' => 'Sibling', 'phone' => '09170000000'], $user->emergency_contact);
        $this->assertSame(['email_on_case_assigned' => true], $user->notifications_config);
    }

    public function test_staff_can_update_staff_profile_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $this->actingAsWithMfa($user)->patch('/profile', [
            'name' => 'Staff User',
            'email' => $user->email,
            'position' => 'Case Manager',
            'department' => 'Operations',
            'office_location' => 'Cebu',
            'bio' => 'Staff bio',
            'emergency_contact' => ['name' => 'Contact', 'relation' => 'Sibling', 'phone' => '09170000000'],
            'notifications_config' => ['email_on_case_assigned' => false],
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Case Manager', $user->position);
        $this->assertSame('Operations', $user->department);
        $this->assertSame('Cebu', $user->office_location);
        $this->assertSame('Staff bio', $user->bio);
        $this->assertSame(['name' => 'Contact', 'relation' => 'Sibling', 'phone' => '09170000000'], $user->emergency_contact);
        $this->assertSame(['email_on_case_assigned' => false], $user->notifications_config);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAsWithMfa($user)
            ->patch('/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
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
