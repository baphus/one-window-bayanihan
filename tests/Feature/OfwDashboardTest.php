<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfwDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    #[Test]
    public function test_ofw_sees_own_cases(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $case1 = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);
        $case2 = CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get('/my-cases');

        $response->assertOk();
        $response->assertJsonPath('component', 'OFW/Dashboard');

        $cases = $response->json('props.cases.data');
        $this->assertCount(2, $cases);
    }

    #[Test]
    public function test_ofw_cannot_see_other_cases(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        // Another client's case
        $otherClient = Client::factory()->create();
        $otherCase = CaseFile::factory()->open()->create([
            'client_id' => $otherClient->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        $response = $this->actingAs($ofwUser)
            ->get("/my-cases/{$otherCase->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function test_ofw_does_not_see_personnel_draft_in_list(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $open = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);
        CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_INTERNAL,
        ]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get('/my-cases');

        $response->assertOk();
        $cases = $response->json('props.cases.data');
        $this->assertCount(1, $cases);
        $this->assertEquals($open->id, $cases[0]['id']);
    }

    #[Test]
    public function test_ofw_cannot_open_personnel_draft_directly(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $draft = CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_INTERNAL,
        ]);

        $response = $this->actingAs($ofwUser)
            ->get("/my-cases/{$draft->id}");

        $response->assertStatus(404);
    }
}
