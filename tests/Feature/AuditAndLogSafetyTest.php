<?php

namespace Tests\Feature;

use App\Logging\RedactSensitiveData;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditAndLogSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_log_processor_blanks_personal_and_credential_values(): void
    {
        $processor = new RedactSensitiveData;
        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'testing',
            level: Level::Warning,
            message: 'failed',
            context: [
                'email' => 'ofw@example.com',
                'password' => 'secret',
                'nested' => ['token' => 'abc', 'kept' => 'ok'],
                'title' => 'Request title stays',
            ],
        );

        $redacted = $processor($record);

        $this->assertSame('[redacted]', $redacted->context['email']);
        $this->assertSame('[redacted]', $redacted->context['password']);
        $this->assertSame('[redacted]', $redacted->context['nested']['token']);
        $this->assertSame('ok', $redacted->context['nested']['kept']);
        $this->assertSame('Request title stays', $redacted->context['title']);
    }

    #[Test]
    public function an_audit_write_failure_never_fails_the_save_that_triggered_it(): void
    {
        $case = CaseFile::factory()->create();
        $before = AuditLog::query()->where('module', 'case')->count();

        // Break the audit chain for this connection only: every audit INSERT
        // now hits a trigger that raises. The business write must still commit.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fail_audit_insert() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit insert blocked for test';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fail_audit_insert BEFORE INSERT ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION fail_audit_insert();
        SQL);

        try {
            $case->update(['status' => 'CLOSED', 'closed_at' => now()]);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_audit_insert ON audit_logs');
            DB::unprepared('DROP FUNCTION IF EXISTS fail_audit_insert()');
        }

        $this->assertSame('CLOSED', $case->fresh()->status);
        $this->assertSame($before, AuditLog::query()->where('module', 'case')->count());
    }

    #[Test]
    public function an_audit_row_is_written_when_a_model_is_created(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'user',
            'entity_id' => $user->id,
        ]);
    }
}
