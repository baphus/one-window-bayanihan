<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseDraftTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CaseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'CASE_MANAGER',
        ]);

        $this->category = CaseCategory::factory()->create();
    }

    public function test_store_creates_draft(): void
    {
        $response = $this->actingAs($this->user)->post(route('cases.store'), [
            'client_type' => 'OFW',
            'category_id' => $this->category->id,
            'client' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan@test.com',
                'sex' => 'Male',
            ],
            'consent' => true,
        ]);

        $response->assertRedirect(route('cases.drafts'));

        $this->assertDatabaseHas('cases', [
            'status' => 'DRAFT',
            'client_id' => null,
        ]);

        $case = CaseFile::where('status', 'DRAFT')->first();
        $this->assertNotNull($case);
        $this->assertNotNull($case->draft_client_data);
        $this->assertEquals('Juan', $case->draft_client_data['first_name']);
        $this->assertEquals('Dela Cruz', $case->draft_client_data['last_name']);
    }

    public function test_store_does_not_create_client(): void
    {
        $initialCount = Client::count();

        $this->actingAs($this->user)->post(route('cases.store'), [
            'client_type' => 'OFW',
            'category_id' => $this->category->id,
            'client' => [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'maria@test.com',
            ],
            'consent' => true,
        ]);

        $this->assertEquals($initialCount, Client::count());
    }

    public function test_store_with_is_draft_false_publishes_case(): void
    {
        $this->actingAs($this->user)->post(route('cases.store'), [
            'client_type' => 'OFW',
            'category_id' => $this->category->id,
            'client' => [
                'first_name' => 'Pedro',
                'last_name' => 'Gomez',
            ],
            'is_draft' => false,
            'consent' => true,
        ]);

        $this->assertDatabaseHas('cases', [
            'status' => 'OPEN',
        ]);
    }

    public function test_publish_creates_client_from_draft(): void
    {
        $case = CaseFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
            'draft_client_data' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan@test.com',
                'sex' => 'Male',
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('cases.publish', $case->id));

        $response->assertRedirect();
        $case->refresh();

        $this->assertEquals('OPEN', $case->status);
        $this->assertNotNull($case->client_id);

        $client = Client::find($case->client_id);
        $this->assertNotNull($client);
        $this->assertEquals('Juan', $client->first_name);
        $this->assertEquals('Dela Cruz', $client->last_name);
    }

    public function test_publish_without_draft_data_creates_open_case_without_client(): void
    {
        $case = CaseFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
            'draft_client_data' => null,
        ]);

        $response = $this->actingAs($this->user)->post(route('cases.publish', $case->id));

        $response->assertRedirect();
        $case->refresh();

        // publishDraft skips client creation when draft_client_data is null,
        // but still transitions the case to OPEN
        $this->assertEquals('OPEN', $case->status);
        $this->assertNull($case->client_id);
    }

    public function test_publish_creates_related_records(): void
    {
        $case = CaseFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
            'draft_client_data' => [
                'first_name' => 'Ana',
                'last_name' => 'Cruz',
                'email' => 'ana@test.com',
                'sex' => 'Female',
                'address' => ['region' => 'NCR', 'province' => 'Metro Manila', 'city_municipality' => 'Manila'],
                'employment' => ['employer_name' => 'ACME Corp', 'position' => 'Engineer', 'country' => 'UAE'],
                'next_of_kin' => ['first_name' => 'Ben', 'last_name' => 'Cruz', 'relationship' => 'Brother'],
            ],
        ]);

        $this->actingAs($this->user)->post(route('cases.publish', $case->id));
        $case->refresh();

        $client = Client::find($case->client_id);
        $this->assertNotNull($client);
        $this->assertEquals('Ana', $client->first_name);

        // Verify related records created by publishDraft
        $this->assertDatabaseHas('client_addresses', [
            'client_id' => $client->id,
            'region' => 'NCR',
            'city_municipality' => 'Manila',
        ]);

        $this->assertDatabaseHas('client_employments', [
            'client_id' => $client->id,
            'employer_name' => 'ACME Corp',
            'position' => 'Engineer',
            'country' => 'UAE',
        ]);

        $this->assertDatabaseHas('next_of_kin', [
            'client_id' => $client->id,
            'first_name' => 'Ben',
            'last_name' => 'Cruz',
            'relationship' => 'Brother',
        ]);
    }

    public function test_json_auto_save_returns_ok(): void
    {
        $case = CaseFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
        ]);

        $response = $this->actingAs($this->user)->put(
            route('cases.save-draft', $case->id),
            [
                'client' => ['first_name' => 'Updated', 'last_name' => 'Name'],
                'client_type' => 'OFW',
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
        ]);
        $response->assertJsonStructure(['ok', 'id', 'saved_at']);
    }

    public function test_json_auto_save_updates_data(): void
    {
        $case = CaseFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'DRAFT',
            'client_type' => 'OFW',
            'draft_client_data' => ['first_name' => 'Old', 'last_name' => 'Name'],
        ]);

        $this->actingAs($this->user)->put(
            route('cases.save-draft', $case->id),
            [
                'client' => ['first_name' => 'New', 'last_name' => 'Name'],
                'client_type' => 'OFW',
            ],
            ['Accept' => 'application/json']
        );

        $case->refresh();
        $this->assertEquals('New', $case->draft_client_data['first_name']);
        $this->assertEquals('Name', $case->draft_client_data['last_name']);
    }

    public function test_json_auto_save_authorization(): void
    {
        $otherUser = User::factory()->create(['role' => 'CASE_MANAGER']);
        $case = CaseFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($otherUser)->put(
            route('cases.save-draft', $case->id),
            ['client_type' => 'OFW'],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(403);
    }

    public function test_json_auto_save_with_existing_client(): void
    {
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'DRAFT',
            'client_id' => $client->id,
            'client_type' => 'OFW',
        ]);

        $response = $this->actingAs($this->user)->put(
            route('cases.save-draft', $case->id),
            [
                'selected_client_id' => $client->id,
                'client_type' => 'OFW',
                'address' => ['region' => 'NCR'],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('client_addresses', [
            'client_id' => $client->id,
            'region' => 'NCR',
        ]);
    }
}
