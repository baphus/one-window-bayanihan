<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CloudinaryAvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOfwUserTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'first_name' => 'Maria', 'last_name' => 'Santos',
            'date_of_birth' => '1990-01-01', 'sex' => 'FEMALE',
            'email' => 'maria@example.com', 'password' => 'Str0ng!Temporary',
            'role' => UserRole::OFW->value, 'contact_number' => '09171234567',
        ];
    }

    public function test_admin_creates_unverified_ofw_with_temporary_password(): void
    {
        $admin = User::factory()->mfaEnabled()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAsWithMfa($admin)->post(route('admin.users.store'), $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect();

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->agcy_id);
        $this->assertSame('09171234567', $user->contact_number);
        $this->assertTrue(Hash::check('Str0ng!Temporary', $user->password));
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $user->id, 'action' => 'CREATE', 'user_id' => $admin->id]);
    }

    public function test_ofw_creation_requires_demographics_and_rejects_duplicate_email(): void
    {
        $admin = User::factory()->mfaEnabled()->create(['role' => UserRole::ADMIN->value]);
        User::factory()->create(['email' => 'maria@example.com']);
        $payload = $this->payload();
        unset($payload['date_of_birth'], $payload['sex']);
        $this->actingAsWithMfa($admin)->post(route('admin.users.store'), $payload)
            ->assertSessionHasErrors(['date_of_birth', 'sex', 'email']);
    }

    public function test_admin_edits_ofw_but_cannot_convert_role_or_verify_email(): void
    {
        $admin = User::factory()->mfaEnabled()->create(['role' => UserRole::ADMIN->value]);
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $payload = $this->payload();
        unset($payload['password']);
        $payload['email'] = $ofw->email;
        $this->actingAsWithMfa($admin)->patch(route('admin.users.update', $ofw), $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame('Maria', $ofw->fresh()->first_name);
        $payload['role'] = UserRole::ADMIN->value;
        $this->patch(route('admin.users.update', $ofw), $payload)->assertSessionHasErrors('role');
        $this->patch(route('admin.users.update', $admin), $this->payload())->assertSessionHasErrors('role');
        $this->patch(route('admin.users.verify', $ofw))->assertForbidden();
        $payload['role'] = UserRole::OFW->value;
        $payload['email'] = 'unproven@example.com';
        $this->patch(route('admin.users.update', $ofw), $payload)->assertSessionHasErrors('email');
        $this->assertSame($ofw->email, $ofw->fresh()->email);
    }

    public function test_password_reset_requires_admin_password_and_revokes_sessions(): void
    {
        $admin = User::factory()->mfaEnabled()->create(['role' => UserRole::ADMIN->value, 'password' => Hash::make('Admin!Password9')]);
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        DB::table('sessions')->insert(['id' => 'old-ofw-session', 'user_id' => $ofw->id, 'payload' => '', 'last_activity' => time()]);
        $payload = $this->payload();
        $payload['email'] = $ofw->email;
        $this->actingAsWithMfa($admin)->patch(route('admin.users.update', $ofw), $payload)
            ->assertSessionHasErrors('current_password');
        $payload['current_password'] = 'Admin!Password9';
        $this->patch(route('admin.users.update', $ofw), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($ofw->fresh()->must_change_password);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-ofw-session']);
    }

    public function test_admin_deactivation_revokes_sessions_and_blocks_access(): void
    {
        $admin = User::factory()->mfaEnabled()->create(['role' => UserRole::ADMIN->value]);
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        DB::table('sessions')->insert(['id' => 'deactivate-session', 'user_id' => $ofw->id, 'payload' => '', 'last_activity' => time()]);
        $payload = $this->payload();
        unset($payload['password']);
        $payload['email'] = $ofw->email;
        $payload['is_active'] = false;
        $this->actingAsWithMfa($admin)->patch(route('admin.users.update', $ofw), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($ofw->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'deactivate-session']);
        $this->actingAs($ofw->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_upload_and_remove_ofw_photo(): void
    {
        $admin = User::factory()->mfaEnabled()->create(['role' => UserRole::ADMIN->value]);
        $photos = $this->mock(CloudinaryAvatarService::class);
        $photos->shouldReceive('uploadImage')->once()->andReturn('https://example.com/ofw-photo.png');
        $photos->shouldReceive('deleteByUrl')->once()->with('https://example.com/ofw-photo.png');
        $this->actingAsWithMfa($admin)->post(route('admin.users.store'), $this->payload() + [
            'profile_picture' => UploadedFile::fake()->image('photo.png', 64, 64),
        ])->assertSessionHasNoErrors();
        $ofw = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('https://example.com/ofw-photo.png', $ofw->getRawOriginal('avatar_url'));
        $payload = $this->payload();
        unset($payload['password']);
        $this->patch(route('admin.users.update', $ofw), $payload + ['remove_profile_picture' => true])->assertSessionHasNoErrors();
        $this->assertNull($ofw->fresh()->getRawOriginal('avatar_url'));
    }

    public function test_non_admin_cannot_create_ofw_and_upload_failure_rolls_back_account(): void
    {
        $staff = User::factory()->mfaEnabled()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->actingAsWithMfa($staff)->post(route('admin.users.store'), $this->payload())->assertForbidden();
        $admin = User::factory()->mfaEnabled()->create(['role' => UserRole::ADMIN->value]);
        $this->mock(CloudinaryAvatarService::class)->shouldReceive('uploadImage')->once()->andThrow(new \RuntimeException('Upload unavailable'));
        $this->actingAsWithMfa($admin)->post(route('admin.users.store'), $this->payload() + [
            'profile_picture' => UploadedFile::fake()->image('photo.png', 64, 64),
        ])->assertSessionHasErrors('profile_picture');
        $this->assertDatabaseMissing('users', ['email' => 'maria@example.com']);
    }

    public function test_temporary_password_blocks_access_until_replaced_then_email_verification_is_required(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value, 'email_verified_at' => null, 'password' => Hash::make('Str0ng!Temporary')]);
        $ofw->must_change_password = true;
        $ofw->save();
        $this->actingAs($ofw)->get(route('dashboard'))->assertRedirect(route('password.temporary.edit'));
        $this->get(route('password.temporary.edit'))->assertOk();
        $this->post(route('password.temporary.update'), [
            'current_password' => 'Str0ng!Temporary', 'password' => 'Str0ng!Temporary', 'password_confirmation' => 'Str0ng!Temporary',
        ])->assertSessionHasErrors('password');
        $this->post(route('password.temporary.update'), [
            'current_password' => 'Str0ng!Temporary', 'password' => 'New!Password123', 'password_confirmation' => 'New!Password123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertFalse($ofw->fresh()->must_change_password);
        $this->assertTrue(Hash::check('New!Password123', $ofw->fresh()->password));
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $ofw->refresh();
        $ofw->email_verified_at = now();
        $ofw->mfa_requirement_override = 'required';
        $ofw->save();
        $this->get(route('dashboard'))->assertRedirect(route('ofw.profile.edit'));
    }

    public function test_temporary_password_endpoint_requires_login_and_current_password(): void
    {
        $this->get(route('password.temporary.edit'))->assertRedirect(route('login'));
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $ofw->must_change_password = true;
        $ofw->save();
        $hash = $ofw->password;
        $this->actingAs($ofw)->post(route('password.temporary.update'), [
            'current_password' => 'wrong', 'password' => 'New!Password123', 'password_confirmation' => 'New!Password123',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame($hash, $ofw->fresh()->password);
        $this->assertTrue($ofw->fresh()->must_change_password);
        $this->post(route('logout'))->assertRedirect('/');
    }
}
