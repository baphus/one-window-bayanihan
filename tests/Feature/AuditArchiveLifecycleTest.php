<?php

namespace Tests\Feature;

use App\Models\AuditArchive;
use App\Models\AuditChainCheckpoint;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditArchiveLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audit-archives');
        AuditLog::truncate();
        AuditArchive::truncate();
        AuditChainCheckpoint::truncate();
    }

    /**
     * Seed entries in chronological order so chain order matches timestamp
     * order (as it does in production).
     */
    private function seedOldLogs(string $period, int $count): void
    {
        foreach (range(1, $count) as $i) {
            AuditLog::create([
                'action' => 'CREATE',
                'module' => 'case',
                'description' => "Old entry {$i}",
                'timestamp' => "{$period}-15 08:00:0".($i % 10),
                'ip_address' => '127.0.0.1',
            ]);
        }
    }

    public function test_archive_creates_bundle_manifest_and_finalized_record(): void
    {
        $period = now()->subDays(500)->format('Y-m');
        $this->seedOldLogs($period, 5);

        $this->artisan('audit:archive')->assertExitCode(0);

        $archive = AuditArchive::where('period', $period)->first();
        $this->assertNotNull($archive);
        $this->assertNotNull($archive->finalized_at);
        $this->assertSame(5, $archive->row_count);

        $disk = Storage::disk('audit-archives');
        $this->assertTrue($disk->exists($archive->path));

        $manifestPath = preg_replace('/\.ndjson\.gz$/', '.manifest.json', $archive->path);
        $this->assertTrue($disk->exists($manifestPath));

        $manifest = json_decode($disk->get($manifestPath), true);
        $this->assertSame(5, $manifest['row_count']);
        $this->assertSame($archive->checksum, $manifest['bundle_sha256']);

        // Bundle checksum matches record and decompressed content is NDJSON
        // with one line per row.
        $raw = $disk->get($archive->path);
        $this->assertSame($archive->checksum, hash('sha256', $raw));
        $lines = array_filter(explode("\n", gzdecode($raw)));
        $this->assertCount(5, $lines);
        $this->assertSame('case', json_decode($lines[0], true)['module']);
    }

    public function test_archive_is_idempotent_per_period(): void
    {
        $period = now()->subDays(500)->format('Y-m');
        $this->seedOldLogs($period, 3);

        $this->artisan('audit:archive')->assertExitCode(0);
        $firstChecksum = AuditArchive::where('period', $period)->value('checksum');

        $this->artisan('audit:archive')->assertExitCode(0);

        $this->assertSame(1, AuditArchive::where('period', $period)->count());
        $this->assertSame($firstChecksum, AuditArchive::where('period', $period)->value('checksum'));
    }

    public function test_prune_refuses_unarchived_entries(): void
    {
        $period = now()->subDays(500)->format('Y-m');
        $this->seedOldLogs($period, 4);

        $this->artisan('audit:prune', ['--force' => true])
            ->expectsOutputToContain('pending archive')
            ->assertExitCode(0);

        $this->assertSame(4, AuditLog::count());
        $this->assertSame(0, AuditChainCheckpoint::count());
    }

    public function test_prune_deletes_archived_entries_records_checkpoint_and_chain_still_verifies(): void
    {
        $period = now()->subDays(500)->format('Y-m');
        $this->seedOldLogs($period, 4);

        // Recent entries that must survive the prune.
        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'referral',
            'description' => 'Recent entry',
            'timestamp' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $this->artisan('audit:archive')->assertExitCode(0);
        $this->artisan('audit:prune', ['--force' => true])
            ->expectsOutputToContain('Pruned 4')
            ->assertExitCode(0);

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('referral', AuditLog::first()->module);

        $checkpoint = AuditChainCheckpoint::first();
        $this->assertNotNull($checkpoint);
        $this->assertSame(AuditLog::first()->prev_hash, $checkpoint->anchor_hash);

        $this->artisan('audit:verify')->assertExitCode(0);
    }

    public function test_prune_skips_period_when_bundle_checksum_mismatches(): void
    {
        $period = now()->subDays(500)->format('Y-m');
        $this->seedOldLogs($period, 2);

        $this->artisan('audit:archive')->assertExitCode(0);

        $archive = AuditArchive::where('period', $period)->first();
        Storage::disk('audit-archives')->put($archive->path, 'corrupted');

        $this->artisan('audit:prune', ['--force' => true])->assertExitCode(0);

        $this->assertSame(2, AuditLog::count());
        $this->assertSame(0, AuditChainCheckpoint::count());
    }

    public function test_prune_only_deletes_contiguous_oldest_prefix(): void
    {
        // An older unarchived period must block pruning of newer archived
        // periods — deleting a newer period first would punch a hole in the
        // chain that no checkpoint can anchor.
        $older = now()->subDays(560)->format('Y-m');
        $newer = now()->subDays(500)->format('Y-m');
        $this->seedOldLogs($older, 2);
        $this->seedOldLogs($newer, 3);

        $this->artisan('audit:archive')->assertExitCode(0);

        // Simulate the older period never having been archived.
        AuditArchive::where('period', $older)->delete();

        $this->artisan('audit:prune', ['--force' => true])
            ->expectsOutputToContain('chain contiguity')
            ->assertExitCode(0);

        $this->assertSame(5, AuditLog::count());
        $this->assertSame(0, AuditChainCheckpoint::count());
    }
}
