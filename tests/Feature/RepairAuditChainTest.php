<?php

namespace Tests\Feature;

use App\Models\AuditChainCheckpoint;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepairAuditChainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuditLog::truncate();
        AuditChainCheckpoint::truncate();
    }

    private function makeChained(array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'action' => 'CREATE',
            'module' => 'case',
            'description' => 'Test entry',
            'timestamp' => now(),
            'ip_address' => '127.0.0.1',
        ], $overrides));
    }

    /**
     * Insert a row exactly the way TestingSeeder does: a raw query-builder
     * insert that bypasses the AuditLog::creating hook, leaving prev_hash NULL.
     */
    private function insertSeededRow(array $overrides = []): string
    {
        $id = (string) Str::uuid();

        DB::table('audit_logs')->insert(array_merge([
            'id' => $id,
            'action' => 'CREATE',
            'module' => 'case',
            'entity_id' => (string) Str::uuid(),
            'description' => null,
            'old_value' => null,
            'new_value' => json_encode(['status' => 'OPEN']),
            'user_id' => null,
            'timestamp' => now(),
            'ip_address' => '127.0.0.1',
            'prev_hash' => null,
        ], $overrides));

        return $id;
    }

    private function assertChainConsistent(string $message = ''): void
    {
        $logs = AuditLog::orderBy('chain_seq')->get();

        $this->assertGreaterThan(0, $logs->count(), 'Expected a non-empty chain. '.$message);
        $this->assertNull($logs[0]->prev_hash, 'Chain root must keep a NULL prev_hash. '.$message);

        for ($i = 1; $i < $logs->count(); $i++) {
            $this->assertSame(
                $logs[$i - 1]->chainDigest(),
                $logs[$i]->prev_hash,
                "Row {$logs[$i]->chain_seq} ({$logs[$i]->description}) is not linked to its predecessor. ".$message
            );
        }
    }

    public function test_repairs_null_prev_hash_block_and_cascades_to_later_rows(): void
    {
        // Real activity rows (chain_seq 1-2): correctly chained via model hooks.
        $this->makeChained(['description' => 'real-1']);
        $this->makeChained(['description' => 'real-2']);

        // Seeder-bypassed block: NULL prev_hash rows.
        $this->insertSeededRow(['description' => 'seed-1']);
        $this->insertSeededRow(['description' => 'seed-2']);
        $this->insertSeededRow(['description' => 'seed-3']);

        // Later real activity, chained against the seeded rows' ORIGINAL digests.
        $postSeed = $this->makeChained(['description' => 'real-3']);
        $originalPostSeedPrev = $postSeed->prev_hash;

        // Sanity: the fixture really is broken the way the dev DB is.
        $this->artisan('audit:verify')->assertExitCode(1);

        $this->artisan('audit:chain:repair', ['--force' => true])
            ->expectsOutputToContain('Pre-flight passed')
            ->expectsOutputToContain('Repair complete')
            ->assertExitCode(0);

        // The whole chain — including the post-seed row whose digest inputs
        // changed — is now consistent.
        $this->assertChainConsistent('after repair');

        // Cascade proof: the post-seed row's stored prev_hash had to move.
        $this->assertNotSame(
            $originalPostSeedPrev,
            $postSeed->fresh()->prev_hash,
            'Post-seed row should have been relinked (cascade), not left untouched.'
        );

        // A full in-database backup of the pre-repair table was taken.
        $backups = DB::select("SELECT tablename FROM pg_tables WHERE tablename LIKE 'audit_logs_backup_%'");
        $this->assertNotEmpty($backups, 'Expected an audit_logs_backup_* table to exist.');
        $this->assertSame(6, DB::table($backups[0]->tablename)->count());

        // The stock verifier agrees the chain is intact now.
        $this->artisan('audit:verify')->assertExitCode(0);
    }

    public function test_dry_run_reports_without_modifying_data(): void
    {
        // Two chained rows first so the chain has started (mirrors the dev
        // DB, where chain_seq 1-5 are healthy); otherwise the lone NULL row
        // would read as a pre-chain legacy prefix, not a repair target.
        $this->makeChained(['description' => 'real-1']);
        $this->makeChained(['description' => 'real-2']);
        $seedId = $this->insertSeededRow(['description' => 'seed-1']);

        $this->artisan('audit:chain:repair', ['--dry-run' => true])
            ->expectsOutputToContain('Pre-flight passed')
            ->expectsOutputToContain('[DRY RUN]')
            ->assertExitCode(0);

        $this->assertNull(AuditLog::where('id', $seedId)->value('prev_hash'));
        $backups = DB::select("SELECT tablename FROM pg_tables WHERE tablename LIKE 'audit_logs_backup_%'");
        $this->assertEmpty($backups, 'Dry run must not create a backup table.');
    }

    public function test_aborts_without_modifying_data_when_non_null_mismatch_exists(): void
    {
        $this->makeChained(['description' => 'real-1']);
        $tampered = $this->makeChained(['description' => 'real-2']);
        $seedId = $this->insertSeededRow(['description' => 'seed-1']);

        // Simulate tampering: rewrite a chained row's prev_hash to a NON-NULL
        // value that matches nothing.
        DB::statement("SET app.allow_audit_mutations = 'true'");
        try {
            DB::table('audit_logs')->where('id', $tampered->id)->update(['prev_hash' => str_repeat('0', 64)]);
        } finally {
            DB::statement("SET app.allow_audit_mutations = ''");
        }

        $before = AuditLog::orderBy('chain_seq')->get()->mapWithKeys(fn ($log) => [$log->id => $log->prev_hash])->toArray();

        $this->artisan('audit:chain:repair', ['--force' => true])
            ->expectsOutputToContain('ABORT')
            ->assertExitCode(1);

        $after = AuditLog::orderBy('chain_seq')->get()->mapWithKeys(fn ($log) => [$log->id => $log->prev_hash])->toArray();

        $this->assertSame($before, $after, 'Aborted repair must not modify any data.');
        $this->assertNull(AuditLog::where('id', $seedId)->value('prev_hash'), 'Seeded row must remain untouched.');
        $this->assertSame(str_repeat('0', 64), AuditLog::where('id', $tampered->id)->value('prev_hash'));

        $backups = DB::select("SELECT tablename FROM pg_tables WHERE tablename LIKE 'audit_logs_backup_%'");
        $this->assertEmpty($backups, 'Aborted repair must not create a backup table.');
    }
}
