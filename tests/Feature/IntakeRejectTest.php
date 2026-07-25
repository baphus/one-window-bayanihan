<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakeRejectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([
                    \App\Http\Middleware\VerifyTurnstile::class,
                    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
                    \Illuminate\Routing\Middleware\ThrottleRequests::class,
                ]);
    }

    #[Test]
    public function test_cm_can_reject_intake_with_reason(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();

        $case = CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
            'user_id' => null,
        ]);

        $response = $this->actingAs($cm)
            ->post("/cases/{$case->id}/reject-intake", [
                'deletion_reason' => 'Incomplete information provided. Unable to process this intake.',
            ]);

        $response->assertRedirect();

        $case->refresh();
        $this->assertTrue($case->is_deleted);
        $this->assertEquals(
            'Incomplete information provided. Unable to process this intake.',
            $case->deletion_reason
        );
    }

    #[Test]
    public function test_reject_requires_reason_min_10(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create();

        $case = CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
            'user_id' => null,
        ]);

        $response = $this->actingAs($cm)
            ->post("/cases/{$case->id}/reject-intake", [
                'deletion_reason' => 'Too short',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('deletion_reason');
    }
}
