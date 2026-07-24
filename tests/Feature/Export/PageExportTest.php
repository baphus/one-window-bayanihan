<?php

namespace Tests\Feature\Export;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    // -------------------------------------------------------------------------
    // Cases
    // -------------------------------------------------------------------------

    #[Test]
    public function cases_export_requires_authentication(): void
    {
        $response = $this->get(route('cases.export-excel'));

        $response->assertStatus(302);
        $response->assertRedirectContains('login');
    }

    #[Test]
    public function cases_export_returns_xlsx(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('cases.export-excel'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'pending']);
        $this->assertArrayHasKey('id', $response->json());

        $this->assertDatabaseHas('generated_documents', [
            'id' => $response->json('id'),
            'user_id' => $user->id,
            'type' => 'cases_export',
            'status' => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // Clients
    // -------------------------------------------------------------------------

    #[Test]
    public function clients_export_requires_authentication(): void
    {
        $response = $this->get(route('clients.export-excel'));

        $response->assertStatus(302);
        $response->assertRedirectContains('login');
    }

    #[Test]
    public function clients_export_returns_xlsx(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('clients.export-excel'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'pending']);
        $this->assertArrayHasKey('id', $response->json());

        $this->assertDatabaseHas('generated_documents', [
            'id' => $response->json('id'),
            'user_id' => $user->id,
            'type' => 'clients_export',
            'status' => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // Referrals
    // -------------------------------------------------------------------------

    #[Test]
    public function referrals_export_requires_authentication(): void
    {
        $response = $this->get(route('referrals.export-excel'));

        $response->assertStatus(302);
        $response->assertRedirectContains('login');
    }

    #[Test]
    public function referrals_export_returns_xlsx(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)->get(route('referrals.export-excel'));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'pending']);
        $this->assertArrayHasKey('id', $response->json());

        $this->assertDatabaseHas('generated_documents', [
            'id' => $response->json('id'),
            'user_id' => $user->id,
            'type' => 'referrals_export',
            'status' => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // Reports Excel export (auth + route only — full call needs PostgreSQL)
    // -------------------------------------------------------------------------

    #[Test]
    public function reports_excel_export_requires_authentication(): void
    {
        $response = $this->get(route('reports.export-excel'));

        $response->assertStatus(302);
        $response->assertRedirectContains('login');
    }

    #[Test]
    public function reports_excel_export_route_exists(): void
    {
        $this->assertNotNull(route('reports.export-excel', [], false));
    }
}
