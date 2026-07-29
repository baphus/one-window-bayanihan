<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::factory()->create();
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    public function test_admin_can_create_user_directly(): void
    {
        $payload = [
            'name' => 'Jane Direct',
            'email' => 'jane.direct@example.com',
            'password' => 'Str0ng!Pass',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'User created successfully.');

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Direct',
            'email' => 'jane.direct@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
            'is_active' => true,
        ]);

        $user = User::where('email', 'jane.direct@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at, 'email_verified_at should be set');
        $this->assertTrue(Hash::check('Str0ng!Pass', $user->password));
    }

    public function test_direct_create_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Dupe User',
                'email' => 'taken@example.com',
                'password' => 'Str0ng!Pass',
                'role' => 'CASE_MANAGER',
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_direct_create_rejects_missing_required_fields(): void
    {
        $this->actingAs($this->admin);

        // Missing name
        $response = $this->post(route('admin.users.store'), [
            'email' => 'user@example.com',
            'password' => 'Str0ng!Pass',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ]);
        $response->assertSessionHasErrors(['name']);

        // Missing email
        $response = $this->post(route('admin.users.store'), [
            'name' => 'No Email',
            'password' => 'Str0ng!Pass',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ]);
        $response->assertSessionHasErrors(['email']);

        // Missing password
        $response = $this->post(route('admin.users.store'), [
            'name' => 'No Pass',
            'email' => 'nopass@example.com',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ]);
        $response->assertSessionHasErrors(['password']);

        // Missing role
        $response = $this->post(route('admin.users.store'), [
            'name' => 'No Role',
            'email' => 'norole@example.com',
            'password' => 'Str0ng!Pass',
            'agcy_id' => $this->agency->id,
        ]);
        $response->assertSessionHasErrors(['role']);
    }

    public function test_direct_create_rejects_invalid_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Bad Role',
                'email' => 'badrole@example.com',
                'password' => 'Str0ng!Pass',
                'role' => 'OFW',
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertSessionHasErrors(['role']);
    }

    public function test_direct_create_rejects_weak_password(): void
    {
        $this->actingAs($this->admin);

        // Too short
        $response = $this->post(route('admin.users.store'), [
            'name' => 'Weak Pass',
            'email' => 'weak1@example.com',
            'password' => 'Sh0rt!',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ]);
        $response->assertSessionHasErrors(['password']);

        // No uppercase
        $response = $this->post(route('admin.users.store'), [
            'name' => 'Weak Pass 2',
            'email' => 'weak2@example.com',
            'password' => 'lowercase1!',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ]);
        $response->assertSessionHasErrors(['password']);

        // No numbers
        $response = $this->post(route('admin.users.store'), [
            'name' => 'Weak Pass 3',
            'email' => 'weak3@example.com',
            'password' => 'NoNumbers!',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ]);
        $response->assertSessionHasErrors(['password']);

        // No symbols
        $response = $this->post(route('admin.users.store'), [
            'name' => 'Weak Pass 4',
            'email' => 'weak4@example.com',
            'password' => 'NoSymbols1',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ]);
        $response->assertSessionHasErrors(['password']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $this->agency->id]);

        $payload = [
            'name' => 'Should Not Create',
            'email' => 'shouldnot@example.com',
            'password' => 'Str0ng!Pass',
            'role' => 'CASE_MANAGER',
            'agcy_id' => $this->agency->id,
        ];

        $response = $this->actingAs($caseManager)
            ->post(route('admin.users.store'), $payload);
        $response->assertForbidden();

        $response = $this->actingAs($agencyUser)
            ->post(route('admin.users.store'), $payload);
        $response->assertForbidden();
    }

    public function test_created_user_can_login_immediately(): void
    {
        $password = 'Str0ng!Pass';

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Login Ready',
                'email' => 'login.ready@example.com',
                'password' => $password,
                'role' => 'CASE_MANAGER',
                'agcy_id' => $this->agency->id,
            ]);

        $response->assertRedirect();

        $this->assertTrue(
            Auth::attempt(['email' => 'login.ready@example.com', 'password' => $password]),
            'Created user must be able to authenticate immediately'
        );
    }

    public function test_admin_can_delete_user(): void
    {
        $target = User::factory()->create([
            'role' => 'AGENCY',
            'is_active' => true,
            'is_deleted' => false,
            'agcy_id' => $this->agency->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $target->id));

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_deleted' => true,
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_delete_nonexistent_user(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $fakeId));

        $response->assertNotFound();
    }
}
