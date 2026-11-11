<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfwProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    private function ofw(): User
    {
        return User::factory()->create(['role' => UserRole::OFW->value, 'profile_completed_at' => null]);
    }

    public function test_settings_expose_account_without_client_profile(): void
    {
        $user = $this->ofw();
        $this->actingAsWithMfa($user)->get(route('ofw.profile.edit'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('OFW/Profile')
                ->where('user.email', $user->email)
                ->where('mfaEnabled', true)
                ->where('mfaRequired', false)
                ->has('mustVerifyEmail')
                ->missing('client')->missing('occupationOptions'));
    }

    public function test_contact_change_updates_only_account_not_historical_case(): void
    {
        $user = $this->ofw();
        $case = CaseFile::factory()->create(['ofw_user_id' => $user->id, 'contact_number' => '09171111111']);
        $this->actingAsWithMfa($user)->put(route('ofw.profile.update'), ['contact_number' => '09182222222'])
            ->assertRedirect(route('ofw.profile.edit'));
        $this->assertSame('09182222222', $user->fresh()->contact_number);
        $this->assertSame('09171111111', $case->fresh()->contact_number);
    }

    public function test_shared_profile_redirects_to_the_minimal_ofw_settings_page(): void
    {
        $this->actingAsWithMfa($this->ofw())->get('/profile')
            ->assertRedirect(route('ofw.profile.edit'));
    }

    public function test_required_ofw_override_can_reach_mfa_enrollment_on_minimal_settings_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::OFW->value,
            'mfa_requirement_override' => 'required',
            'mfa_enabled_at' => null,
            'mfa_secret' => null,
        ]);

        $this->actingAs($user)->get(route('ofw.profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('OFW/Profile')->where('mfaRequired', true));
        $this->get(route('ofw.dashboard'))->assertRedirect(route('ofw.profile.edit'));
    }

    public function test_shared_profile_patch_rejects_staff_fields_for_ofw(): void
    {
        $user = $this->ofw();

        $this->actingAsWithMfa($user)->patch('/profile', [
            'department' => 'Changed', 'position' => 'Changed', 'office_location' => 'Changed',
            'bio' => 'Changed', 'emergency_contact' => ['name' => 'Changed'],
        ])->assertSessionHasErrors(['department', 'position', 'office_location', 'bio', 'emergency_contact']);

        $this->assertNull($user->fresh()->department);
        $this->assertNull($user->fresh()->position);
    }

    public function test_settings_reject_case_identity_and_representative_fields(): void
    {
        $this->actingAsWithMfa($this->ofw())->put(route('ofw.profile.update'), [
            'address' => ['street' => 'Changed'],
            'employment' => ['employer_name' => 'Changed'], 'next_of_kin' => [['first_name' => 'Other']],
            'type' => 'NEXT_OF_KIN', 'email' => 'unverified@example.test',
        ])->assertSessionHasErrors(['address', 'employment', 'next_of_kin', 'type', 'email']);
    }

    public function test_identity_edit_recomposes_the_display_name(): void
    {
        $user = $this->ofw();

        $this->actingAsWithMfa($user)->put(route('ofw.profile.update'), [
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Cruz', 'suffix' => 'Jr.',
            'date_of_birth' => '1990-01-01', 'sex' => 'FEMALE',
        ])->assertRedirect(route('ofw.profile.edit'))->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('Maria Santos Cruz, Jr.', $fresh->name);
        $this->assertSame('FEMALE', $fresh->sex);
        $this->assertSame('1990-01-01', $fresh->date_of_birth->toDateString());
    }

    public function test_password_change_invalidates_other_sessions_and_remember_token(): void
    {
        $user = $this->ofw();
        $rememberToken = $user->remember_token;
        $sessionId = $this->otherSession($user);
        $this->actingAsWithMfa($user)->put(route('ofw.profile.update'), [
            'current_password' => 'P@ssw0rd!', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('ofw.profile.edit'));
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = $this->ofw();
        $this->actingAsWithMfa($user)->put(route('ofw.profile.update'), [
            'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('current_password');
        $this->put(route('ofw.profile.update'), [
            'current_password' => 'wrong', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('P@ssw0rd!', $user->fresh()->password));
    }

    public function test_contact_only_save_keeps_password_sessions_and_remember_token(): void
    {
        $user = $this->ofw();
        $rememberToken = $user->remember_token;
        $sessionId = $this->otherSession($user);
        $this->actingAsWithMfa($user)->put(route('ofw.profile.update'), [
            'contact_number' => '09182222222', 'current_password' => '', 'password' => '', 'password_confirmation' => '',
        ])->assertRedirect(route('ofw.profile.edit'));
        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
        $this->assertSame($rememberToken, $user->fresh()->remember_token);
        $this->assertTrue(Hash::check('P@ssw0rd!', $user->fresh()->password));
    }

    public function test_ofw_can_delete_account_with_current_password_without_deleting_case_history(): void
    {
        $user = $this->ofw();
        $case = CaseFile::factory()->create(['ofw_user_id' => $user->id]);
        $sessionId = $this->otherSession($user);

        $this->actingAsWithMfa($user)->delete('/profile', ['password' => 'P@ssw0rd!'])
            ->assertRedirect('/');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertNotNull($case->fresh());
        $this->assertSame($user->id, $case->fresh()->ofw_user_id);
    }

    public function test_contact_update_is_not_audited_as_sensitive_account_data(): void
    {
        $user = $this->ofw();
        $this->actingAsWithMfa($user)->put(route('ofw.profile.update'), ['contact_number' => '09182222222'])
            ->assertRedirect(route('ofw.profile.edit'));
        $this->assertSame('09182222222', $user->fresh()->contact_number);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'UPDATE', 'entity_id' => $user->id]);
    }

    private function otherSession(User $user): string
    {
        $id = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit', 'payload' => 'test', 'last_activity' => time(),
        ]);

        return $id;
    }
}
