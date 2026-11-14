<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_registration_redirects_without_creating_account_or_claiming_staff_case(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create([
            'user_id' => $manager->id,
            'ofw_user_id' => null,
            'email' => 'tracked@example.test',
        ]);
        $binding = ['tracker_number' => $case->tracker_number, 'email' => $case->email];

        $this->withSession([TrackingService::SESSION_KEY => $binding])
            ->postJson(route('track.register'), [
                'password' => 'Str0ngPass!123', 'password_confirmation' => 'Str0ngPass!123',
            ])->assertOk()->assertExactJson(['success' => true, 'redirect' => route('register')]);

        $this->assertDatabaseMissing('users', ['email' => 'tracked@example.test']);
        $this->assertDatabaseHas('cases', [
            'id' => $case->id, 'user_id' => $manager->id, 'ofw_user_id' => null,
        ]);
        $this->assertSame($binding, session(TrackingService::SESSION_KEY));
        $this->assertNull(session('pending_registration'));
        $this->assertGuest();
    }

    public function test_matching_account_is_not_signed_in_or_associated_with_staff_case(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value, 'email' => 'existing@example.test']);
        $case = CaseFile::factory()->create(['email' => $user->email, 'ofw_user_id' => null]);

        $this->withSession([TrackingService::SESSION_KEY => [
            'tracker_number' => $case->tracker_number, 'email' => $user->email,
        ]])->postJson(route('track.register'))->assertOk()
            ->assertExactJson(['success' => true, 'redirect' => route('register')]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->assertNull($case->fresh()->ofw_user_id);
        $this->assertSame(1, User::where('email', $user->email)->count());
        $this->assertGuest();
    }

    public function test_unverified_request_only_gets_public_registration_route(): void
    {
        $this->postJson(route('track.register'), ['email' => 'forged@example.test', 'client_id' => 'forged'])
            ->assertOk()->assertExactJson(['success' => true, 'redirect' => route('register')]);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }
}
