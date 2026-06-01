<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    public function test_admin_can_delete_service(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $agency = Agency::factory()->create();
        $service = Service::create([
            'name' => 'Test Service',
            'description' => 'A test service for deletion',
            'agcy_id' => $agency->id,
            'processing_days' => 7,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.services.destroy', $service->id));

        $response->assertRedirect(route('admin.services.index'));

        $this->assertSoftDeleted($service);
    }

    public function test_admin_cannot_delete_nonexistent_service(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($admin)->delete(route('admin.services.destroy', $fakeId));

        $response->assertNotFound();
    }
}
