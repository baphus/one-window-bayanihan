<?php

namespace Tests\Feature\Export;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    #[Test]
    public function unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('admin.data-export.index'));

        $response->assertStatus(302);
        $response->assertRedirectContains('login');
    }

    #[Test]
    public function case_manager_cannot_access_admin_export_page(): void
    {
        $caseManager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($caseManager)->get(route('admin.data-export.index'));

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_access_export_page(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->get(route('admin.data-export.index'));

        $response->assertStatus(200);
    }

    #[Test]
    public function admin_export_returns_xlsx_headers(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->get(route('admin.data-export.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            '.xlsx',
            $response->headers->get('Content-Disposition')
        );
    }

    #[Test]
    public function admin_export_filename_matches_pattern(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $response = $this->actingAs($admin)->get(route('admin.data-export.export'));

        $this->assertStringContainsString(
            'bayanihan-full-export-',
            $response->headers->get('Content-Disposition')
        );
    }
}
