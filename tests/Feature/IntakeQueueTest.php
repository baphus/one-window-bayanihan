<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    #[Test]
    public function test_intake_queue_shows_self_filed_drafts(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();

        $case = CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
            'user_id' => null,
        ]);

        $response = $this->actingAs($cm)
            ->withHeader('X-Inertia', 'true')
            ->get('/cases/intake-queue');

        $response->assertOk();
        $response->assertJsonPath('component', 'Case/IntakeQueue');
    }

    #[Test]
    public function test_intake_queue_not_accessible_by_agency(): void
    {
        $agencyUser = User::factory()->create(['role' => 'AGENCY']);

        $response = $this->actingAs($agencyUser)
            ->get('/cases/intake-queue');

        $response->assertStatus(403);
    }

    #[Test]
    public function test_intake_queue_not_accessible_by_ofw(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($ofwUser)
            ->get('/cases/intake-queue');

        $response->assertStatus(403);
    }
}
