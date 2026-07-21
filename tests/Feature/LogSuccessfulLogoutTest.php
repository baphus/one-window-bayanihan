<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Listeners\LogSuccessfulLogout;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogSuccessfulLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function countLogoutEntries(User $user): int
    {
        return AuditLog::where('action', AuditAction::LOGOUT->value)
            ->where('user_id', $user->getKey())
            ->count();
    }

    public function test_logout_creates_one_security_audit_entry(): void
    {
        $user = User::factory()->create();

        (new LogSuccessfulLogout)->handle(new Logout('web', $user));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LOGOUT->value,
            'module' => 'auth',
            'user_id' => $user->getKey(),
            'entity_id' => $user->getKey(),
            'category' => 'security',
        ]);
        $this->assertSame(1, $this->countLogoutEntries($user));
    }

    public function test_logout_without_a_user_is_ignored(): void
    {
        (new LogSuccessfulLogout)->handle(new Logout('web', null));

        $this->assertSame(0, AuditLog::where('action', AuditAction::LOGOUT->value)->count());
    }

    public function test_rapid_consecutive_logouts_suppress_duplicate(): void
    {
        $user = User::factory()->create();
        $listener = new LogSuccessfulLogout;
        $event = new Logout('web', $user);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertSame(1, $this->countLogoutEntries($user));
    }

    public function test_delayed_logouts_create_separate_entries(): void
    {
        $user = User::factory()->create();

        AuditLog::create([
            'action' => AuditAction::LOGOUT->value,
            'module' => 'auth',
            'entity_id' => $user->getKey(),
            'user_id' => $user->getKey(),
            'timestamp' => now()->subSeconds(10),
        ]);

        (new LogSuccessfulLogout)->handle(new Logout('web', $user));

        $this->assertSame(2, $this->countLogoutEntries($user));
    }
}
