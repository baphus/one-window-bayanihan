<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the REAL redaction path — AuditLog::saving() persisting to the
 * database and reading back — rather than a re-implemented copy of the logic.
 * A regression in AuditLog::boot()/redact() now fails these tests.
 */
class AuditRedactionTest extends TestCase
{
    use RefreshDatabase;

    private function persist(array $newValue): AuditLog
    {
        $log = AuditLog::create([
            'action' => AuditAction::UPDATE->value,
            'module' => AuditModule::USER->value,
            'entity_id' => '00000000-0000-0000-0000-000000000001',
            'new_value' => $newValue,
            'timestamp' => now(),
        ]);

        return $log->fresh();
    }

    public function test_exact_sensitive_keys_are_redacted(): void
    {
        $log = $this->persist([
            'password' => 'plain-text',
            'remember_token' => 'abc',
            'mfa_secret' => 'JBSWY3DP',
            'otp' => '123456',
            'session_id' => 'sess_abc',
            'signature' => 'sig_xyz',
            'csrf' => 'tok',
        ]);

        foreach (['password', 'remember_token', 'mfa_secret', 'otp', 'session_id', 'signature', 'csrf'] as $key) {
            $this->assertSame('[REDACTED]', $log->new_value[$key], "{$key} was not redacted");
        }
    }

    public function test_sensitive_keys_holding_arrays_are_fully_redacted(): void
    {
        // Regression guard: array_walk_recursive would have left these element
        // values exposed because it only visits scalar leaves. A matching key
        // must redact its whole value, array or not.
        $log = $this->persist([
            'mfa_recovery_codes' => ['CODE-AAAA', 'CODE-BBBB'],
            'authorization' => ['Bearer', 'tok-SECRET'],
            'tokens' => ['refresh' => 'rt-SECRET', 'access' => 'at-SECRET'],
            'kept' => ['a', 'b'],
        ]);

        $this->assertSame('[REDACTED]', $log->new_value['mfa_recovery_codes']);
        $this->assertSame('[REDACTED]', $log->new_value['authorization']);
        $this->assertSame('[REDACTED]', $log->new_value['tokens']);
        // A non-sensitive array key is preserved intact.
        $this->assertSame(['a', 'b'], $log->new_value['kept']);
    }

    public function test_pattern_matched_keys_are_redacted(): void
    {
        $log = $this->persist([
            'access_token' => 'a',
            'refresh_token' => 'b',
            'api_key' => 'c',
            'client_secret' => 'd',
            'authorization' => 'Bearer x',
            'cookie' => 'sid=1',
            'private_key' => '-----BEGIN-----',
            'user_credential' => 'e',
        ]);

        foreach (array_keys($log->new_value) as $key) {
            $this->assertSame('[REDACTED]', $log->new_value[$key], "{$key} was not redacted");
        }
    }

    public function test_non_sensitive_values_are_preserved(): void
    {
        $log = $this->persist([
            'email' => 'user@example.com',
            'name' => 'Jane Cruz',
            'case_number' => 'C-2026-1',
            'note' => 'benign note',
        ]);

        $this->assertSame('user@example.com', $log->new_value['email']);
        $this->assertSame('Jane Cruz', $log->new_value['name']);
        $this->assertSame('C-2026-1', $log->new_value['case_number']);
        $this->assertSame('benign note', $log->new_value['note']);
    }

    public function test_redaction_is_recursive_and_case_insensitive(): void
    {
        $log = $this->persist([
            'profile' => [
                'PASSWORD' => 'x',
                'nested' => ['api_key' => 'y', 'display_name' => 'ok'],
            ],
        ]);

        $this->assertSame('[REDACTED]', $log->new_value['profile']['PASSWORD']);
        $this->assertSame('[REDACTED]', $log->new_value['profile']['nested']['api_key']);
        $this->assertSame('ok', $log->new_value['profile']['nested']['display_name']);
    }

    public function test_redaction_policy_is_driven_by_config(): void
    {
        config()->set('audit.redact.keys', ['clearance_code']);
        config()->set('audit.redact.patterns', []);

        $log = $this->persist(['clearance_code' => 'top', 'password' => 'kept-now']);

        // Only the configured key is redacted; the built-in default no longer applies.
        $this->assertSame('[REDACTED]', $log->new_value['clearance_code']);
        $this->assertSame('kept-now', $log->new_value['password']);
    }
}
