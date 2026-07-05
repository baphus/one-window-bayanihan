<?php

namespace Tests\Feature;

use App\Listeners\LogSuccessfulLogin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogSuccessfulLoginTest extends TestCase
{
    use RefreshDatabase;

    private function countLoginEntries(User $user): int
    {
        return AuditLog::where('action', 'LOGIN')
            ->where('user_id', $user->getKey())
            ->count();
    }

    public function test_single_login_creates_one_audit_entry(): void
    {
        $user = User::factory()->create();
        $event = new Login('web', $user, false);
        $listener = new LogSuccessfulLogin;

        $listener->handle($event);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOGIN',
            'user_id' => $user->getKey(),
        ]);
        $this->assertSame(1, $this->countLoginEntries($user));
    }

    public function test_rapid_consecutive_logins_suppress_duplicate(): void
    {
        $user = User::factory()->create();
        $event = new Login('web', $user, false);
        $listener = new LogSuccessfulLogin;

        $listener->handle($event);
        $listener->handle($event);

        $this->assertSame(1, $this->countLoginEntries($user));
    }

    public function test_delayed_logins_create_separate_entries(): void
    {
        $user = User::factory()->create();
        $event = new Login('web', $user, false);
        $listener = new LogSuccessfulLogin;

        // Create a LOGIN entry with a timestamp older than the 5-second dedup window
        AuditLog::create([
            'action' => 'LOGIN',
            'module' => 'auth',
            'entity_id' => $user->getKey(),
            'user_id' => $user->getKey(),
            'timestamp' => now()->subSeconds(10),
        ]);

        // Second login should create a new entry since no recent one exists
        $listener->handle($event);

        $this->assertSame(2, $this->countLoginEntries($user));
    }
}
