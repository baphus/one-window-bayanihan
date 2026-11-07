<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateSystemReport;
use App\Models\GeneratedDocument;
use App\Models\User;
use App\Services\Reports\ReportsExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateSystemReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_false_storage_write_marks_document_failed_and_throws(): void
    {
        $document = GeneratedDocument::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'system_report_pdf',
            'filename' => 'report.pdf',
            'status' => 'pending',
        ]);
        $tempPath = sys_get_temp_dir().'/'.Str::uuid();
        file_put_contents($tempPath, 'pdf-content');

        $reports = $this->createMock(ReportsExportService::class);
        Storage::shouldReceive('disk')->with('object-storage')->andReturnSelf();
        Storage::shouldReceive('put')->once()->andReturn(false);

        $job = new class((string) $document->id, $tempPath) extends GenerateSystemReport
        {
            public function __construct(string $documentId, private readonly string $sourcePath)
            {
                parent::__construct($documentId, 'system_report_pdf');
            }

            protected function generatePdf(ReportsExportService $service, string $path): void
            {
                copy($this->sourcePath, $path);
            }
        };

        try {
            $job->handle($reports);
            $this->fail('Expected failed object-storage write to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Failed to store generated report.', $exception->getMessage());
        }

        $this->assertSame('failed', $document->fresh()->status);
        $this->assertNull($document->fresh()->path);
    }

    public function test_report_job_dispatched(): void
    {
        Bus::fake();

        GenerateSystemReport::dispatch((string) Str::uuid(), 'system_report_pdf');

        Bus::assertDispatched(GenerateSystemReport::class);
    }

    public function test_report_job_has_backoff(): void
    {
        $job = new GenerateSystemReport((string) Str::uuid(), 'system_report_pdf');

        $this->assertSame(3, $job->tries);
        $this->assertSame(300, $job->timeout);
        $this->assertSame([30, 120, 600], $job->backoff());
    }
}
