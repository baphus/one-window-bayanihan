<?php

namespace Tests\Feature;

use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntakePublishTest extends TestCase
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
    public function test_cm_can_publish_self_filed_draft(): void
    {
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1985-06-15',
            'sex' => 'MALE',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
        ]);

        // Add address so publishing validation passes
        \App\Models\ClientAddress::create([
            'client_id' => $client->id,
            'region' => 'Region VII',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Lahug',
        ]);

        // Create a category so publishing validation passes
        $category = CaseCategory::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Labor Dispute',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $case = CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
            'user_id' => null,
            'client_type' => 'OFW',
            'category_id' => $category->id,
            'summary' => 'Need help with unpaid wages for 3 months.',
            'consent_given_at' => now(),
        ]);

        // Attach category to pivot table
        $case->categories()->attach($category->id);

        $response = $this->actingAs($cm)
            ->post("/cases/{$case->id}/publish");

        $response->assertRedirect();

        $case->refresh();
        $this->assertEquals('OPEN', $case->status);
        $this->assertEquals($cm->id, $case->user_id);
        $this->assertEquals($cm->id, $case->intake_reviewed_by);
    }
}
