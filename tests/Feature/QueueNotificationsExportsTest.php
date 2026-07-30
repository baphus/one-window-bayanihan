<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueueNotificationsExportsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Export Endpoints Return Synchronous Streams
    // -------------------------------------------------------------------------

    #[Test]
    public function cases_export_returns_xlsx_stream(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('cases.export-excel'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('Content-Disposition');
    }

    #[Test]
    public function clients_export_returns_xlsx_stream(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('clients.export-excel'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('Content-Disposition');
    }

    #[Test]
    public function referrals_export_returns_xlsx_stream(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('referrals.export-excel'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('Content-Disposition');
    }

    #[Test]
    public function reports_pdf_export_returns_a_download(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('reports.export-pdf', [
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition');
    }

    #[Test]
    public function reports_excel_export_returns_xlsx_stream(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('reports.export-excel', [
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('Content-Disposition');
    }
}
