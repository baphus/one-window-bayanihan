<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateSystemReport;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateSystemReportTest extends TestCase
{
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
