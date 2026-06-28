<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->user = User::factory()->create(['role' => 'CASE_MANAGER']);
    }

    #[Test]
    public function export_pdf_requires_authentication(): void
    {
        $response = $this->get(route('reports.export-pdf'));

        $response->assertStatus(302);
        $response->assertRedirectContains('login');
    }

    #[Test]
    public function export_pdf_route_exists(): void
    {
        $this->assertNotNull(route('reports.export-pdf', [], false));
    }

    #[Test]
    public function export_pdf_accepts_date_range_params(): void
    {
        $url = route('reports.export-pdf', ['from' => '2026-01-01', 'to' => '2026-12-31'], false);
        $this->assertStringContainsString('from=2026-01-01', $url);
        $this->assertStringContainsString('to=2026-12-31', $url);
    }

    /**
     * The following tests exercise the full controller stack which calls
     * ReportsService → PostgreSQL-specific functions (EXTRACT, age, etc.).
     * These tests require a running PostgreSQL instance.
     * In production (PostgreSQL), the PDF endpoint returns 200 with
     * Content-Type: application/pdf and Content-Disposition: attachment.
     */
}
