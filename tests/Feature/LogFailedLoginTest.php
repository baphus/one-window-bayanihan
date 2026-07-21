<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Listeners\LogFailedLogin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit coverage for the failed-login listener, mirroring LogSuccessfulLoginTest.
 * (Previously only exercised indirectly through the HTTP path.)
 */
class LogFailedLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_records_identifier_but_no_user_attribution(): void
    {
        $user = User::factory()->create(['email' => 'known@example.com']);

        (new LogFailedLogin)->handle(new Failed('web', $user, [
            'email' => 'known@example.com',
            'password' => 'super-secret-password',
        ]));

        $log = AuditLog::where('action', AuditAction::LOGIN_FAILED->value)->firstOrFail();

        // Attempt is attributed to no signed-in actor, but points at the user row.
        $this->assertNull($log->user_id);
        $this->assertSame($user->getKey(), $log->entity_id);
        $this->assertSame('auth', $log->module);
        $this->assertSame('security', $log->category);
        $this->assertStringContainsString('known@example.com', $log->description);
    }

    public function test_failed_login_never_stores_password_material(): void
    {
        (new LogFailedLogin)->handle(new Failed('web', null, [
            'email' => 'ghost@example.com',
            'password' => 'this-must-never-be-logged',
        ]));

        $log = AuditLog::where('action', AuditAction::LOGIN_FAILED->value)->firstOrFail();

        $serialized = json_encode([$log->description, $log->old_value, $log->new_value]);
        $this->assertStringNotContainsString('this-must-never-be-logged', $serialized);
    }

    public function test_failed_login_for_unknown_account_has_null_entity(): void
    {
        (new LogFailedLogin)->handle(new Failed('web', null, ['email' => 'nobody@example.com']));

        $log = AuditLog::where('action', AuditAction::LOGIN_FAILED->value)->firstOrFail();

        $this->assertNull($log->entity_id);
        $this->assertNull($log->user_id);
        $this->assertStringContainsString('nobody@example.com', $log->description);
    }
}
