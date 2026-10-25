<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\CloudinaryAvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Batch-3 LOW-severity console/upload fixes:
 *  - CleanupLogs must survive filesystem failures per file instead of aborting.
 *  - RepairAuditChain's chunked streaming must still repair seeded broken chains.
 *  - CloudinaryAvatarService must reject extension/MIME-spoofed non-image content.
 */
class ConsoleAndUploadLowsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Isolate storage/logs so the log-source globs see only this test's
     * fixtures. Single laravel.log is also cleared so the fallback source
     * cannot bleed rows into later tests.
     */
    private function clearTestLogFiles(): void
    {
        foreach (array_merge(glob(storage_path('logs/laravel-*.log')), [storage_path('logs/laravel.log')]) as $file) {
            if (is_string($file) && is_file($file)) {
                // Drop any read-only attribute a previous interrupted run left
                // behind, otherwise this run's File::put() fixture writes fail.
                @chmod($file, 0666);
                @unlink($file);
            }
        }
    }

    #[Test]
    public function cleanup_skips_problem_files_without_aborting(): void
    {
        $this->clearTestLogFiles();

        // A DIRECTORY matching the glob: is_file() is false, so the entry is
        // skipped (never crashes) and shows up in the skip summary.
        $fakeEntry = storage_path('logs/laravel-2024-01-01.log');
        File::ensureDirectoryExists($fakeEntry);

        // An old READ-ONLY file: Windows refuses to unlink it (E_WARNING →
        // skip); Linux unlinks successfully through the writable directory —
        // both paths must complete without throwing.
        $protectedPath = storage_path('logs/laravel-2024-01-02.log');
        File::put($protectedPath, 'old content');
        touch($protectedPath, now()->subDays(10)->timestamp);
        chmod($protectedPath, 0444);

        // A regular old file: removable on every platform.
        $removablePath = storage_path('logs/laravel-2024-01-03.log');
        File::put($removablePath, 'old content');
        touch($removablePath, now()->subDays(10)->timestamp);

        // Summary convention: assert start-of-line fragments only —
        // PendingCommand matches per rendered doWrite chunk, so mid-line
        // fragments can cross chunk boundaries (same reason the existing
        // RepairAuditChainTest asserts only line-start fragments).
        // The per-file skip contract itself is asserted on disk below: the
        // glob-matched directory survives, the platform-locked file survives
        // on Windows, and neither failure aborted the run (exit code 0).
        try {
            $this->artisan('logs:cleanup')
                ->expectsOutputToContain('Cleanup: deleted '.(PHP_OS_FAMILY === 'Windows' ? 1 : 2).' log files')
                ->expectsOutputToContain(
                    'Cleanup: skipped '.(PHP_OS_FAMILY === 'Windows' ? 2 : 1).' file(s) that could not be removed'
                )
                ->assertExitCode(0);
        } finally {
            if (file_exists($protectedPath)) {
                // Lift the read-only attribute so the files can be removed.
                @chmod($protectedPath, 0666);
            }
        }

        // The glob-matched directory must survive (skipped, not deleted).
        $this->assertDirectoryExists($fakeEntry);

        // Windows cannot delete a read-only file; the command reports the
        // skip and moves on instead of aborting mid-pass.
        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertFileExists($protectedPath, 'A Windows-locked file must be skipped, not abort the run.');
        } else {
            $this->assertFileDoesNotExist($protectedPath);
        }
    }

    #[Test]
    public function repair_audit_chain_repairs_seeded_broken_chain_in_small_chunks(): void
    {
        // Smallest chunk forces the streaming walk through many chunk
        // boundaries while carrying per-chain state across them.
        $chunkSize = 1;

        // Real activity rows, correctly chained via the model hooks.
        $this->makeChained(['description' => 'real-1']);
        $this->makeChained(['description' => 'real-2']);

        // Seeder-bypassed block: raw DB insert leaves prev_hash NULL.
        $this->insertSeededRow(['description' => 'seed-1']);
        $this->insertSeededRow(['description' => 'seed-2']);

        // Later real row chained against the seeded rows' ORIGINAL digests —
        // proves the repair cascades past the relinked block.
        $postSeed = $this->makeChained(['description' => 'real-3']);
        $originalPostSeedPrev = $postSeed->prev_hash;

        $this->artisan('audit:chain:repair', ['--force' => true, '--chunk' => $chunkSize])
            ->expectsOutputToContain('Pre-flight passed')
            ->expectsOutputToContain('Repair complete')
            ->assertExitCode(0);

        $this->assertChainConsistent();

        // Cascade proof: the post-seed row had to be relinked too.
        $this->assertNotSame(
            $originalPostSeedPrev,
            $postSeed->fresh()->prev_hash,
            'Post-seed row should have been relinked (cascade), not left untouched.'
        );
    }

    #[Test]
    public function avatar_upload_rejects_text_content_disguised_as_jpeg(): void
    {
        $service = $this->makeRealAvatarService();

        $spoofed = UploadedFile::fake()->createWithContent('avatar.jpg', 'plain text, definitely not a JPEG image');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Uploaded file is not a valid image.');

        $service->validateImageFile($spoofed);
    }

    #[Test]
    public function avatar_upload_accepts_a_real_image(): void
    {
        $service = $this->makeRealAvatarService();

        $valid = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $rejection = null;

        try {
            $service->validateImageFile($valid);
        } catch (\RuntimeException $exception) {
            $rejection = $exception;
        }

        $this->assertNull($rejection, 'A real image whose content matches the allowlist must pass validation.');
    }

    /**
     * The container binds a Cloudinary test double via tests/TestCase; the
     * MIME/content validation lives on the REAL service, so build one directly.
     */
    private function makeRealAvatarService(): CloudinaryAvatarService
    {
        config(['services.cloudinary.url' => 'cloudinary://test-key:test-secret@test-cloud']);

        return new CloudinaryAvatarService;
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

    private function assertChainConsistent(): void
    {
        $logs = AuditLog::orderBy('chain_seq')->get();

        $this->assertGreaterThan(0, $logs->count(), 'Expected a non-empty chain.');
        $this->assertNull($logs[0]->prev_hash, 'Chain root must keep a NULL prev_hash.');

        for ($i = 1; $i < $logs->count(); $i++) {
            $this->assertSame(
                $logs[$i - 1]->chainDigest(),
                $logs[$i]->prev_hash,
                "Row {$logs[$i]->chain_seq} is not linked to its predecessor."
            );
        }
    }

    protected function tearDown(): void
    {
        // Remove this test's fixtures (including the glob-matching directory).
        foreach (glob(storage_path('logs/laravel-*.log')) as $entry) {
            if (is_string($entry) && is_dir($entry)) {
                @rmdir($entry);
            }
        }
        $this->clearTestLogFiles();

        parent::tearDown();
    }
}
