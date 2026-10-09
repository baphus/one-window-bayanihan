<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditAppendOnlyGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The append-only trigger is created by a migration, which RefreshDatabase
        // does not replay; assert it exists so the test fails loudly rather than
        // silently passing because the guard is missing entirely.
        $this->assertTrue(
            $this->triggerExists(),
            'The audit_logs append-only trigger must exist for this test to mean anything.'
        );
    }

    private function triggerExists(): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM pg_trigger t
             JOIN pg_class c ON c.oid = t.tgrelid
             WHERE c.relname = 'audit_logs' AND NOT t.tgisinternal"
        );

        return $rows !== [];
    }

    #[Test]
    public function the_guard_flag_is_cleared_after_both_backfill_commands(): void
    {
        $this->artisan('audit:backfill-categories')->assertSuccessful();
        $this->artisan('audit:backfill-descriptions', ['--since' => '1970-01-01'])->assertSuccessful();

        // A plain AuditLog update must be rejected once the command is done. If
        // the backfill leaked a bare SET into the connection's session, this
        // update succeeds and the append-only trigger no longer protects the
        // table for every later writer on the same connection.
        $log = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'cases',
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'user_id' => null,
            'timestamp' => now(),
        ]);

        $this->expectException(\Throwable::class);

        $log->update(['description' => 'tampered after the backfill finished']);
    }

    #[Test]
    public function a_plain_audit_log_update_is_rejected_without_the_guard(): void
    {
        $log = AuditLog::create([
            'action' => 'CREATE',
            'module' => 'cases',
            'entity_id' => '22222222-2222-2222-2222-222222222222',
            'user_id' => null,
            'timestamp' => now(),
        ]);

        $this->expectException(\Throwable::class);

        $log->update(['description' => 'tampered with no guard set']);
    }
}
