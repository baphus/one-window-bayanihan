<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use Database\Seeders\AgencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgencySeeder::class);
    }

    public function test_profile_page_loads_with_agency_data(): void
    {
        $user = User::factory()->create([
            'agcy_id' => Agency::where('slug', 'dmw')->first()->id,
        ]);

        $response = $this->actingAsWithMfa($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->has('mfaEnabled')
                ->has('defaultAgency')
                ->has('auth.user.agency')
            );
    }

    public function test_admin_without_mfa_sees_enrollment_requirement_and_cannot_save(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->actingAs($admin)->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->where('mfaEnabled', false)
                ->where('mfaRequired', true)
            );

        $this->actingAs($admin)->patch(route('profile.update'), [
            'first_name' => 'Changed',
            'email' => $admin->email,
        ])->assertRedirect(route('profile.edit'));
        $this->assertNotSame('Changed', $admin->fresh()->first_name);
    }

    public function test_profile_update_with_all_new_fields(): void
    {
        $user = User::factory()->create([
            'agcy_id' => Agency::where('slug', 'dmw')->first()->id,
        ]);

        $this->actingAsWithMfa($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'email' => $user->email,
                'position' => 'Senior Case Worker',
                'department' => 'Field Operations',
                'office_location' => 'Cebu City Main Office',
                'bio' => 'Experienced case manager dedicated to serving OFWs.',
                'emergency_contact' => [
                    'name' => 'Jane Doe',
                    'relation' => 'Spouse',
                    'phone' => '09179876543',
                ],
                'notifications_config' => [
                    'email_on_case_assigned' => false,
                    'email_on_case_status_change' => true,
                    'email_on_referral' => false,
                    'in_app_notifications' => true,
                ],
                'timezone' => 'Asia/Manila',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertEquals('Senior Case Worker', $user->position);
        $this->assertEquals('Field Operations', $user->department);
        $this->assertEquals('Cebu City Main Office', $user->office_location);
        $this->assertEquals('Experienced case manager dedicated to serving OFWs.', $user->bio);
        $this->assertSame([
            'name' => 'Jane Doe',
            'relation' => 'Spouse',
            'phone' => '09179876543',
        ], $user->emergency_contact);
        $this->assertSame([
            'email_on_case_assigned' => false,
            'email_on_case_status_change' => true,
            'email_on_referral' => false,
            'in_app_notifications' => true,
        ], $user->notifications_config);
        $this->assertEquals('Asia/Manila', $user->timezone);
    }

    public function test_profile_update_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAsWithMfa($user)
            ->patch(route('profile.update'), [
                'first_name' => '',
                'last_name' => '',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email']);
    }

    public function test_guest_cannot_access_profile(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'), [])->assertRedirect(route('login'));
    }
}
