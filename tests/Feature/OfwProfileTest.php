<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\NextOfKin;
use App\Models\User;
use App\Notifications\CaseUpdated;
use App\Services\OfwProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfwProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    private function ofwUserFor(Client $client): User
    {
        return User::factory()->create([
            'role' => UserRole::OFW->value,
            'client_id' => $client->id,
        ]);
    }

    #[Test]
    public function test_ofw_sees_own_profile_record(): void
    {
        $client = Client::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Soriano',
            'sex' => 'FEMALE',
            'contact_number' => '09171234567',
        ]);
        ClientAddress::create(['client_id' => $client->id, 'region' => 'VII', 'city_municipality' => 'Cebu City']);
        ClientEmployment::create(['client_id' => $client->id, 'employer_name' => 'ACME Ltd', 'country' => 'Saudi Arabia']);
        $nok = NextOfKin::create(['client_id' => $client->id, 'first_name' => 'Juan', 'last_name' => 'Soriano', 'is_primary' => true, 'sort_order' => 0]);
        $user = $this->ofwUserFor($client);

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->get('/my-cases/profile');

        $response->assertOk();
        $response->assertJsonPath('component', 'OFW/Profile');
        $this->assertEquals('Maria', $response->json('props.client.first_name'));
        $this->assertEquals('Cebu City', $response->json('props.client.address.city_municipality'));
        $this->assertEquals('ACME Ltd', $response->json('props.client.employment.employer_name'));
        $this->assertEquals($nok->id, $response->json('props.client.next_of_kin.0.id'));
    }

    #[Test]
    public function test_ofw_profile_page_omits_client_when_account_has_none(): void
    {
        $user = User::factory()->create(['role' => UserRole::OFW->value]);

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->get('/my-cases/profile');

        $response->assertOk();
        $this->assertNull($response->json('props.client'));
    }

    #[Test]
    public function test_ofw_can_update_contact_number(): void
    {
        $client = Client::factory()->create(['contact_number' => '09171234567']);
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'contact_number' => '09182222222',
        ])->assertRedirect(route('ofw.profile.edit'));

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'contact_number' => '09182222222']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'contact_number' => '09182222222']);
    }

    #[Test]
    public function test_ofw_can_update_address(): void
    {
        $client = Client::factory()->create();
        ClientAddress::create(['client_id' => $client->id, 'region' => 'VII', 'city_municipality' => 'Old City']);
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'address' => ['region' => 'NCR', 'province' => 'Metro Manila', 'city_municipality' => 'Manila', 'barangay' => 'Ermita', 'street' => 'Roxas Blvd'],
        ])->assertRedirect(route('ofw.profile.edit'));

        $address = ClientAddress::where('client_id', $client->id)->first();
        $this->assertEquals('NCR', $address->region);
        $this->assertEquals('Manila', $address->city_municipality);
        $this->assertEquals('Roxas Blvd', $address->street);
    }

    #[Test]
    public function test_ofw_can_update_employment(): void
    {
        $client = Client::factory()->create();
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'employment' => [
                'employer_name' => 'ACME Ltd',
                'position' => 'Domestic Worker',
                'country' => 'Saudi Arabia',
                'start_date' => '2024-01-01',
                'end_date' => '2025-01-01',
                'last_position' => 'Nanny',
                'date_of_arrival' => '2025-02-01',
            ],
        ])->assertRedirect(route('ofw.profile.edit'));

        $employment = ClientEmployment::where('client_id', $client->id)->first();
        $this->assertEquals('ACME Ltd', $employment->employer_name);
        $this->assertEquals('2024-01-01', $employment->start_date->toDateString());
        $this->assertEquals('2025-02-01', $employment->date_of_arrival->toDateString());
    }

    #[Test]
    public function test_ofw_can_replace_next_of_kin(): void
    {
        $client = Client::factory()->create();
        NextOfKin::create(['client_id' => $client->id, 'first_name' => 'Old', 'last_name' => 'Kin', 'is_primary' => true, 'sort_order' => 0]);
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'next_of_kin' => [
                ['first_name' => 'Juan', 'last_name' => 'Soriano', 'relationship' => 'Spouse', 'phone_number' => '09170000000', 'email' => 'juan@example.com'],
                ['first_name' => 'Ana', 'last_name' => 'Soriano', 'relationship' => 'Sibling'],
            ],
        ])->assertRedirect(route('ofw.profile.edit'));

        $this->assertDatabaseMissing('next_of_kin', ['first_name' => 'Old']);
        $noks = NextOfKin::where('client_id', $client->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $noks);
        $this->assertTrue($noks[0]->is_primary);
        $this->assertFalse($noks[1]->is_primary);
        $this->assertEquals('juan@example.com', $noks[0]->email);
    }

    #[Test]
    public function test_ofw_can_clear_all_next_of_kin(): void
    {
        $client = Client::factory()->create();
        NextOfKin::create(['client_id' => $client->id, 'first_name' => 'Old', 'last_name' => 'Kin', 'is_primary' => true]);
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'next_of_kin' => [],
        ])->assertRedirect(route('ofw.profile.edit'));

        $this->assertDatabaseMissing('next_of_kin', ['client_id' => $client->id]);
    }

    #[Test]
    public function test_next_of_kin_survives_a_failure_part_way_through_the_profile_update(): void
    {
        $client = Client::factory()->create(['contact_number' => '09171234567']);
        NextOfKin::create([
            'client_id' => $client->id,
            'first_name' => 'Juan',
            'last_name' => 'Soriano',
            'relationship' => 'Spouse',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $user = $this->ofwUserFor($client);

        // Fail on the first replacement row, i.e. after the existing rows have
        // already been force-deleted.
        NextOfKin::creating(function () {
            throw new \RuntimeException('simulated next of kin insert failure');
        });

        try {
            app(OfwProfileService::class)->updateClientProfile($user, [
                'contact_number' => '09182222222',
                'next_of_kin' => [
                    ['first_name' => 'Ana', 'last_name' => 'Soriano', 'relationship' => 'Sibling'],
                ],
            ]);

            $this->fail('Expected the profile update to abort.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated next of kin insert failure', $e->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.NextOfKin::class);
        }

        // The force-deleted row is restored by the rollback and the replacement
        // is not partially applied.
        $this->assertDatabaseHas('next_of_kin', [
            'client_id' => $client->id,
            'first_name' => 'Juan',
            'last_name' => 'Soriano',
        ]);
        $this->assertDatabaseMissing('next_of_kin', ['client_id' => $client->id, 'first_name' => 'Ana']);

        // The earlier contact number write in the same request is rolled back too.
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'contact_number' => '09171234567']);
    }

    #[Test]
    public function test_ofw_can_change_password(): void
    {
        $client = Client::factory()->create();
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'current_password' => 'P@ssw0rd!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('ofw.profile.edit'));

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    #[Test]
    public function test_ofw_password_change_invalidates_other_sessions_and_remember_token(): void
    {
        $client = Client::factory()->create();
        $user = $this->ofwUserFor($client);
        $originalRememberToken = $user->remember_token;

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $this->actingAs($user)->put('/my-cases/profile', [
            'current_password' => 'P@ssw0rd!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('ofw.profile.edit'));

        // Rotating the hash on its own leaves every other browser signed in,
        // which makes the change pointless: the old password stays usable
        // somewhere the OFW never sees.
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertNotSame($originalRememberToken, $user->fresh()->remember_token);
    }

    #[Test]
    public function test_profile_update_without_a_password_keeps_other_sessions_alive(): void
    {
        $client = Client::factory()->create();
        $user = $this->ofwUserFor($client);
        $originalRememberToken = $user->remember_token;
        $sessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $this->actingAs($user)->put('/my-cases/profile', [
            'contact_number' => '09182222222',
        ])->assertRedirect(route('ofw.profile.edit'));

        // Only the password branch is a credential change; saving a contact
        // number must not sign the OFW out of their other devices.
        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
        $this->assertSame($originalRememberToken, $user->fresh()->remember_token);
    }

    #[Test]
    public function test_current_password_is_required_when_changing_password(): void
    {
        $client = Client::factory()->create();
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('current_password');
    }

    #[Test]
    public function test_blank_password_submission_is_allowed_without_current_password(): void
    {
        $client = Client::factory()->create();
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
            'contact_number' => '09182222222',
        ])->assertRedirect(route('ofw.profile.edit'));

        $this->assertTrue(Hash::check('P@ssw0rd!', $user->fresh()->password));
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'contact_number' => '09182222222']);
    }

    #[Test]
    public function test_profile_update_is_audited(): void
    {
        $client = Client::factory()->create();
        ClientAddress::create(['client_id' => $client->id, 'city_municipality' => 'Old City']);
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'address' => ['city_municipality' => 'New City'],
        ])->assertRedirect(route('ofw.profile.edit'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE',
            'module' => 'client_address',
            'entity_id' => ClientAddress::where('client_id', $client->id)->first()->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function test_profile_update_notifies_case_manager(): void
    {
        $client = Client::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
        ]);
        $user = $this->ofwUserFor($client);

        $this->actingAs($user)->put('/my-cases/profile', [
            'contact_number' => '09182222222',
        ])->assertRedirect(route('ofw.profile.edit'));

        $this->assertDatabaseHas('notifications', [
            'type' => CaseUpdated::class,
            'notifiable_id' => $manager->id,
        ]);
    }
}
