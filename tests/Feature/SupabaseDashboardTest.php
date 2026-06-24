<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupabaseDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->admin = User::factory()->create(['role' => 'ADMIN']);
    }

    #[Test]
    public function test_page_loads(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Inertia', 'true')
            ->get(route('admin.system.supabase'));

        $response->assertStatus(200);
    }

    #[Test]
    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);
        $response = $this->actingAs($user)
            ->get(route('admin.system.supabase'));

        $response->assertForbidden();
    }
}
