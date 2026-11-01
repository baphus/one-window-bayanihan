<?php

namespace Tests\Feature;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Http\Middleware\TurnstileMiddleware;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditSecurityEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            TurnstileMiddleware::class,
        ]);
    }

    public function test_failed_login_is_logged_without_password_material(): void
    {
        $user = User::factory()->create([
            'email' => 'target@example.com',
            'password' => Hash::make('CorrectHorse1!'),
        ]);

        AuditLog::truncate();

        $this->post('/login', [
            'email' => 'target@example.com',
            'password' => 'wrong-password-123',
        ]);

        $entry = AuditLog::where('action', 'LOGIN_FAILED')->first();

        $this->assertNotNull($entry);
        $this->assertSame('security', $entry->category);
        $this->assertStringContainsString('target@example.com', $entry->description);

        $serialized = json_encode([$entry->description, $entry->old_value, $entry->new_value]);
        $this->assertStringNotContainsString('wrong-password-123', $serialized);
        $this->assertStringNotContainsString('CorrectHorse1!', $serialized);
    }

    public function test_mfa_disable_is_logged_as_security_event(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectHorse1!'),
            'mfa_secret' => 'SECRETSECRET',
            'mfa_enabled_at' => now(),
        ]);

        AuditLog::truncate();

        $this->actingAs($user)->postJson('/profile/mfa/disable', [
            'password' => 'CorrectHorse1!',
        ]);

        $entry = AuditLog::where('module', 'mfa')
            ->where('description', 'like', '%disabled two-factor%')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('security', $entry->category);
        $this->assertStringNotContainsString('SECRETSECRET', json_encode([$entry->description, $entry->new_value]));
    }

    public function test_security_audit_logger_redacts_secret_material(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'mfa',
            'user_id' => $user->id,
            'new_value' => ['mfa_secret' => 'SHOULD-NOT-SURVIVE', 'note' => 'kept'],
            'timestamp' => now(),
        ]);

        $entry = AuditLog::where('module', 'mfa')->orderBy('timestamp', 'desc')->first();

        $this->assertSame('[REDACTED]', $entry->new_value['mfa_secret']);
        $this->assertSame('kept', $entry->new_value['note']);
    }

    public function test_session_termination_is_logged(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $target = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->actingAs($admin);

        // Written through the same path the application uses. The previous
        // version of this test called SecurityAuditLogger, a class with no
        // production caller — it asserted on a writer nothing invoked, which is
        // how SESSION rows came to be missing without any test going red.
        AuditLogger::revokeSessions()
            ->by($admin)
            ->forEntity($target, $target->name)
            ->module(AuditModule::SESSION)
            ->context(null, ['reason' => 'admin_revoked', 'old_session_count' => 2])
            ->write();

        $entry = AuditLog::where('module', 'session')->first();

        $this->assertNotNull($entry);
        $this->assertSame('security', $entry->category);
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame('admin_revoked', $entry->new_value['reason']);
    }
}
