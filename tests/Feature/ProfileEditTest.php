<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Database\Seeders\AgencySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AgencySeeder::class);
    }

    public function test_profile_page_loads_with_agency_data(): void
    {
        $user = User::factory()->create([
            'agcy_id' => Agency::where('slug', 'dmw')->first()->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->has('mfaEnabled')
                ->has('defaultAgency')
                ->has('auth.user.agency')
            );
    }

    public function test_profile_update_with_all_new_fields(): void
    {
        $user = User::factory()->create([
            'agcy_id' => Agency::where('slug', 'dmw')->first()->id,
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Name',
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
                'timezone' => 'Asia/Manila',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertEquals('Senior Case Worker', $user->position);
        $this->assertEquals('Field Operations', $user->department);
        $this->assertEquals('Cebu City Main Office', $user->office_location);
        $this->assertEquals('Experienced case manager dedicated to serving OFWs.', $user->bio);
        $this->assertEquals('Asia/Manila', $user->timezone);
    }

    public function test_profile_update_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => '',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_guest_cannot_access_profile(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'), [])->assertRedirect(route('login'));
    }
}
