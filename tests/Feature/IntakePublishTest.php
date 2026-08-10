<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTurnstile;
use App\Mail\ClientUpdateMail;
use App\Mail\IntakePublishedMail;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
            VerifyTurnstile::class,
            PreventRequestForgery::class,
            ThrottleRequests::class,
        ]);
    }

    #[Test]
    public function test_publishing_applies_reviewer_corrections_to_the_existing_client(): void
    {
        // A self-filed intake already has a client, created by IntakeService at
        // submission. publishDraft only built a client when client_id was empty,
        // so corrections a case manager made on the review screen stayed in
        // draft_client_data and never reached the clients row — a case could open
        // with clients.sex still NULL after the reviewer had set it.
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);

        $client = Client::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1990-05-15',
            'sex' => null,
            'contact_number' => '+639171234567',
            'email' => 'ofw@example.com',
        ]);

        ClientAddress::create([
            'client_id' => $client->id,
            'region' => 'Region VII',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Lahug',
        ]);

        $category = CaseCategory::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Wage Claim',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $case = CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
            'user_id' => null,
            'client_type' => 'OFW',
            'category_id' => $category->id,
            'summary' => 'Employer withheld three months of salary.',
            'consent_given_at' => now(),
            // What the reviewer corrected, plus a field they left untouched.
            'draft_client_data' => [
                'first_name' => 'Juan',
                'sex' => 'Female',
                'contact_number' => '+639170000000',
            ],
        ]);

        $case->categories()->attach($category->id);

        $this->actingAs($cm)->post("/cases/{$case->id}/publish")->assertRedirect();

        $client->refresh();

        // Corrections land on the client, sex normalised to upper case as it is
        // when a client is created during publication.
        $this->assertSame('FEMALE', $client->sex);
        $this->assertSame('+639170000000', $client->contact_number);

        // Fields absent from the draft are left alone rather than blanked.
        $this->assertSame('Dela Cruz', $client->last_name);
        $this->assertSame('ofw@example.com', $client->email);

        $this->assertSame('OPEN', $case->fresh()->status);
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
        ClientAddress::create([
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

    #[Test]
    public function test_publishing_sends_only_the_acceptance_email(): void
    {
        // When an intake is reviewed and published, the OFW should receive the
        // dedicated acceptance email only — not also a generic ClientUpdateMail,
        // which used to fire because notifyOfw() queues one by default.
        $cm = User::factory()->create(['role' => 'CASE_MANAGER']);
        $client = Client::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'date_of_birth' => '1992-03-20',
            'sex' => 'FEMALE',
            'contact_number' => '09171234568',
            'email' => 'maria@example.com',
        ]);

        ClientAddress::create([
            'client_id' => $client->id,
            'region' => 'Region VII',
            'city_municipality' => 'Cebu City',
            'barangay' => 'Lahug',
        ]);

        $category = CaseCategory::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Wage Claim',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $case = CaseFile::factory()->draft()->create([
            'client_id' => $client->id,
            'source' => CaseFile::SOURCE_SELF_FILED,
            'user_id' => null,
            'client_type' => 'OFW',
            'category_id' => $category->id,
            'summary' => 'Unpaid overtime.',
            'consent_given_at' => now(),
        ]);

        $case->categories()->attach($category->id);

        $this->actingAs($cm)
            ->post("/cases/{$case->id}/publish")
            ->assertRedirect();

        Mail::assertQueued(IntakePublishedMail::class, 1);
        Mail::assertQueued(ClientUpdateMail::class, 0);

        // The in-app notification behind the bell still lands.
        $this->assertDatabaseHas('case_notifications', [
            'case_id' => $case->id,
            'client_email' => 'maria@example.com',
            'type' => 'intake_published',
            'title' => 'Case Accepted',
        ]);
        $this->assertSame(1, CaseNotification::where('case_id', $case->id)->where('type', 'intake_published')->count());
    }
}
