<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevokeMfaEnrolledSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_force_or_confirmation_and_only_removes_enrolled_sessions(): void
    {
        $enrolled = User::factory()->mfaEnabled()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'enrolled-session', 'user_id' => $enrolled->id, 'payload' => 'x', 'last_activity' => now()->timestamp],
            ['id' => 'other-session', 'user_id' => $other->id, 'payload' => 'x', 'last_activity' => now()->timestamp],
        ]);

        $this->artisan('mfa:revoke-enrolled-sessions', ['--force' => true])
            ->expectsOutput('Revoked 1 database session(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('sessions', ['id' => 'enrolled-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    }
}
