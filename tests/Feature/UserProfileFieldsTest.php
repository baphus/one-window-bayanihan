<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_user_can_have_all_new_fields_set(): void
    {
        $user = User::factory()->create([
            'position' => 'Senior Case Manager',
            'department' => 'Field Operations',
            'office_location' => 'Cebu City Office',
            'bio' => 'Experienced case manager with 5 years of service.',
            'emergency_contact' => ['name' => 'John Doe', 'relation' => 'Spouse', 'phone' => '09171234567'],
            'timezone' => 'Asia/Manila',
        ]);

        $this->assertEquals('Senior Case Manager', $user->position);
        $this->assertEquals('Field Operations', $user->department);
        $this->assertEquals('Cebu City Office', $user->office_location);
        $this->assertEquals('Experienced case manager with 5 years of service.', $user->bio);
        $this->assertEquals(['name' => 'John Doe', 'relation' => 'Spouse', 'phone' => '09171234567'], $user->emergency_contact);
        $this->assertEquals('Asia/Manila', $user->timezone);
    }

    public function test_mfa_fields_are_null_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->mfa_secret);
        $this->assertNull($user->mfa_recovery_codes);
        $this->assertNull($user->mfa_enabled_at);
    }

    public function test_mfa_fields_can_be_set(): void
    {
        $user = User::factory()->create([
            'mfa_secret' => 'TESTMFAKEYSECRET123',
            'mfa_recovery_codes' => ['code1', 'code2'],
            'mfa_enabled_at' => now(),
        ]);

        $this->assertEquals('TESTMFAKEYSECRET123', $user->mfa_secret);
        $this->assertEquals(['code1', 'code2'], $user->mfa_recovery_codes);
        $this->assertNotNull($user->mfa_enabled_at);
    }
}
