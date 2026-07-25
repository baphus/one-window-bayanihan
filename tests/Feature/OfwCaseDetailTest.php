<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfwCaseDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    #[Test]
    public function test_ofw_sees_case_detail(): void
    {
        $client = Client::factory()->create();
        $ofwUser = User::factory()->create([
            'role' => 'OFW',
            'client_id' => $client->id,
        ]);

        $case = CaseFile::factory()->open()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        $response = $this->actingAs($ofwUser)
            ->withHeader('X-Inertia', 'true')
            ->get("/my-cases/{$case->id}");

        $response->assertOk();
        $response->assertJsonPath('component', 'OFW/CaseDetail');
    }
}
