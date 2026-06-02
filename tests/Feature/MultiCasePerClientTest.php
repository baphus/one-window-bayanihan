<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\Client;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCasePerClientTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CaseService $service;

    private CaseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->service = app(CaseService::class);
        $this->category = CaseCategory::factory()->create(['is_active' => true]);
    }

    public function test_can_create_second_case_for_same_client(): void
    {
        $client = Client::factory()->create();
        // Create first case
        $this->service->createCase([
            'client_type' => 'OFW',
            'selected_client_id' => $client->id,
            'category_id' => $this->category->id,
        ], $this->user->id);

        // Create second case for same client — should NOT throw 422
        $case2 = $this->service->createCase([
            'client_type' => 'OFW',
            'selected_client_id' => $client->id,
            'category_id' => $this->category->id,
        ], $this->user->id);

        $this->assertEquals(2, $client->caseFiles()->count());
        $this->assertDatabaseHas('cases', ['id' => $case2->id, 'client_id' => $client->id]);
    }

    public function test_new_case_does_not_overwrite_client_name(): void
    {
        $client = Client::factory()->create([
            'first_name' => 'Original',
            'last_name' => 'Name',
            'email' => 'original@example.com',
        ]);

        $this->service->createCase([
            'client_type' => 'OFW',
            'selected_client_id' => $client->id,
            'category_id' => $this->category->id,
        ], $this->user->id);

        $client->refresh();
        $this->assertEquals('Original', $client->first_name);
        $this->assertEquals('original@example.com', $client->email);
    }

    public function test_category_id_is_stored_on_case(): void
    {
        $case = $this->service->createCase([
            'client_type' => 'OFW',
            'selected_client_id' => Client::factory()->create()->id,
            'category_id' => $this->category->id,
        ], $this->user->id);

        $this->assertEquals($this->category->id, $case->category_id);
    }

    public function test_category_id_is_optional(): void
    {
        $case = $this->service->createCase([
            'client_type' => 'OFW',
            'selected_client_id' => Client::factory()->create()->id,
        ], $this->user->id);

        $this->assertNull($case->category_id);
    }

    public function test_case_loads_category_relation(): void
    {
        $case = $this->service->createCase([
            'client_type' => 'OFW',
            'selected_client_id' => Client::factory()->create()->id,
            'category_id' => $this->category->id,
        ], $this->user->id);

        $loaded = $this->service->getCase($case->id);
        $this->assertTrue($loaded->relationLoaded('category'));
        $this->assertEquals($this->category->name, $loaded->category->name);
    }
}
