<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_mfa_secret_is_guarded_from_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'test',
            'email' => 'a@b.com',
            'password' => 'P@ssw0rd!',
            'role' => 'CASE_MANAGER',
            'mfa_secret' => 'test',
        ]);

        $this->assertNull($user->mfa_secret);
    }

    public function test_mfa_recovery_codes_are_guarded_from_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'test',
            'email' => 'b@c.com',
            'password' => 'P@ssw0rd!',
            'role' => 'CASE_MANAGER',
            'mfa_recovery_codes' => ['code1', 'code2'],
        ]);

        $this->assertNull($user->mfa_recovery_codes);
    }
}
