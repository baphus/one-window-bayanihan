<?php

namespace Tests\Feature\Export;

use App\Jobs\ExportDataToExcel;
use App\Models\GeneratedDocument;
use App\Models\User;
use App\Notifications\DownloadReady;
use App\Services\Reports\ReportsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The documents disk is configured with 'throw' => false, so a failed write
 * returns false instead of raising. The job used to ignore that return value
 * and mark the document "completed" with a path and size while no file
 * existed — the export looked successful and only the download failed.
 */
class ExportStorageFailureTest extends TestCase
{
    use RefreshDatabase;

    private function pendingExport(User $user): GeneratedDocument
    {
        return GeneratedDocument::create([
            'user_id' => $user->id,
            'type' => 'cases_export',
            'filename' => 'cases-export-test.xlsx',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function a_rejected_storage_write_raises_instead_of_reporting_success(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $document = $this->pendingExport($user);

        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('supabase')->andReturn($disk);

        $job = new ExportDataToExcel('cases_export', [], $user->id, $document->id);

        try {
            $job->handle(app(ReportsExportService::class));
            $this->fail('Expected the job to raise when the generated file cannot be stored.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('storage', $e->getMessage());
        }

        $this->assertSame('pending', $document->fresh()->status);
        $this->assertNotSame('completed', $document->fresh()->status);
    }

    #[Test]
    public function the_document_is_marked_failed_and_the_user_notified_when_storage_rejects_the_write(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $document = $this->pendingExport($user);

        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('supabase')->andReturn($disk);

        $job = new ExportDataToExcel('cases_export', [], $user->id, $document->id);

        try {
            $job->handle(app(ReportsExportService::class));
            $this->fail('Expected the job to raise when the generated file cannot be stored.');
        } catch (RuntimeException $e) {
            // The queue calls failed() once the job has exhausted its retries.
            $job->failed($e);
        }

        $this->assertSame('failed', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->error_message);
        Notification::assertSentTo($user, DownloadReady::class);
    }

    #[Test]
    public function a_successful_write_still_completes_the_document(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $document = $this->pendingExport($user);

        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturnTrue();
        Storage::shouldReceive('disk')->with('supabase')->andReturn($disk);

        $job = new ExportDataToExcel('cases_export', [], $user->id, $document->id);
        $job->handle(app(ReportsExportService::class));

        $fresh = $document->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->path);
        $this->assertGreaterThan(0, $fresh->file_size);
        Notification::assertSentTo($user, DownloadReady::class);
    }
}
