<?php

namespace Tests\Feature;

use App\Models\AuditChainCheckpoint;
use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditChainIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuditLog::truncate();
        AuditChainCheckpoint::truncate();
    }

    private function makeLog(array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'action' => 'CREATE',
            'module' => 'case',
            'description' => 'Test entry',
            'timestamp' => now(),
            'ip_address' => '127.0.0.1',
        ], $overrides));
    }

    public function test_sequential_creates_link_the_chain(): void
    {
        $first = $this->makeLog(['timestamp' => now()->subMinutes(2)]);
        $second = $this->makeLog(['timestamp' => now()->subMinute()]);
        $third = $this->makeLog();

        $this->assertNull($first->prev_hash);
        $this->assertSame($first->fresh()->chainDigest(), $second->prev_hash);
        $this->assertSame($second->fresh()->chainDigest(), $third->prev_hash);
    }

    public function test_concurrent_create_is_serialized_by_advisory_lock(): void
    {
        // Hold the chain lock from a second connection, then prove a create
        // on the main connection cannot proceed while it is held.
        config(['database.connections.pgsql_lock_test' => config('database.connections.pgsql')]);
        $second = DB::connection('pgsql_lock_test');

        $second->beginTransaction();
        $second->statement("SELECT pg_advisory_xact_lock(hashtext('audit_log_chain'))");

        try {
            DB::statement("SET LOCAL lock_timeout = '500ms'");

            try {
                $this->makeLog();
                $this->fail('Create should have blocked on the advisory lock');
            } catch (QueryException $e) {
                $this->assertStringContainsString('lock timeout', strtolower($e->getMessage()));
            }
        } finally {
            $second->rollBack();
            DB::connection('pgsql_lock_test')->disconnect();
        }
    }

    public function test_verify_passes_on_intact_chain(): void
    {
        $this->makeLog(['timestamp' => now()->subMinutes(2)]);
        $this->makeLog(['timestamp' => now()->subMinute()]);
        $this->makeLog();

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Audit chain intact: 3 entries verified')
            ->assertExitCode(0);
    }

    public function test_same_second_creates_chain_in_insertion_order(): void
    {
        // Timestamps are second-precision and UUIDs are random, so ordering
        // by (timestamp, id) forked the chain for same-second writes. The
        // chain_seq sequence must keep insertion order regardless.
        $ts = now();
        foreach (range(1, 5) as $i) {
            $this->makeLog(['timestamp' => $ts, 'description' => "Same-second {$i}"]);
        }

        $this->artisan('audit:verify')
            ->expectsOutputToContain('Audit chain intact: 5 entries verified')
            ->assertExitCode(0);
    }

    public function test_verify_tolerates_pre_chain_legacy_prefix(): void
    {
        // Rows created before the hash chain existed have null prev_hash.
        $this->makeLog(['timestamp' => now()->subMinutes(5)]);
        $this->makeLog(['timestamp' => now()->subMinutes(4)]);

        DB::statement("SET app.allow_audit_mutations = 'true'");
        try {
            DB::table('audit_logs')->update(['prev_hash' => null]);
        } finally {
            DB::statement("SET app.allow_audit_mutations = ''");
        }

        // Post-feature rows chain normally over the legacy tail.
        $this->makeLog(['timestamp' => now()->subMinute()]);
        $this->makeLog();

        $this->artisan('audit:verify')
            ->expectsOutputToContain('pre-chain legacy entries')
            ->assertExitCode(0);
    }

    public function test_verify_detects_tampered_row(): void
    {
        $this->makeLog(['timestamp' => now()->subMinutes(2)]);
        $middle = $this->makeLog(['timestamp' => now()->subMinute()]);
        $this->makeLog();

        DB::statement("SET app.allow_audit_mutations = 'true'");
        try {
            // ip_address is part of the chain digest; description is not
            DB::table('audit_logs')->where('id', $middle->id)->update(['ip_address' => '10.6.6.6']);
        } finally {
            DB::statement("SET app.allow_audit_mutations = ''");
        }

        $this->artisan('audit:verify')->assertExitCode(1);
    }

    public function test_verify_anchors_at_checkpoint_after_prune(): void
    {
        $oldest = $this->makeLog(['timestamp' => now()->subMinutes(3)]);
        $pruned = $this->makeLog(['timestamp' => now()->subMinutes(2)]);
        $this->makeLog(['timestamp' => now()->subMinute()]);
        $this->makeLog();

        AuditChainCheckpoint::create([
            'anchor_hash' => $pruned->fresh()->chainDigest(),
            'pruned_through' => $pruned->timestamp,
            'created_at' => now(),
        ]);

        DB::statement("SET app.allow_audit_mutations = 'true'");
        try {
            DB::table('audit_logs')->whereIn('id', [$oldest->id, $pruned->id])->delete();
        } finally {
            DB::statement("SET app.allow_audit_mutations = ''");
        }

        $this->artisan('audit:verify')
            ->expectsOutputToContain('anchored at checkpoint')
            ->assertExitCode(0);
    }
}
