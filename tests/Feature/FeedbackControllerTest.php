<?php

namespace Tests\Feature;

use App\Http\Controllers\FeedbackController;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Agency $agency;

    private Client $client;

    private CaseFile $case;

    private Referral $referral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'ADMIN']);
        $this->agency = Agency::factory()->create();
        $this->client = Client::factory()->create();
        $this->case = CaseFile::factory()->create([
            'client_id' => $this->client->id,
        ]);
        $this->referral = Referral::factory()->create([
            'case_id' => $this->case->id,
            'agcy_id' => $this->agency->id,
            'required_services' => 'Medical Assistance',
        ]);
    }

    #[Test]
    public function submit_valid_returns_201(): void
    {
        $token = (string) Str::uuid();

        CaseNotification::create([
            'case_id' => $this->case->id,
            'client_email' => $this->client->email,
            'type' => 'feedback_request',
            'title' => 'Feedback Request',
            'message' => 'Please provide your feedback.',
            'data' => [
                'tracking_token' => $token,
                'referral_id' => $this->referral->id,
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('feedbacks.submit'), [
            'tracking_token' => $token,
            'servqual_responses' => [
                [
                    'dimension' => 'Tangibles',
                    'question_text' => 'Modern equipment?',
                    'expectation' => 4,
                    'perception' => 5,
                ],
            ],
            'overall_rating' => 5,
            'comments' => 'Excellent!',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Feedback submitted successfully',
        ]);
        $this->assertDatabaseHas('feedback', [
            'case_id' => $this->case->id,
            'agency_id' => $this->agency->id,
            'referral_id' => $this->referral->id,
            'service_name' => 'Medical Assistance',
            'overall_rating' => 5,
            'comments' => 'Excellent!',
        ]);
    }

    #[Test]
    public function submit_invalid_token_returns_400(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('feedbacks.submit'), [
            'tracking_token' => 'non-existent-token',
            'servqual_responses' => [
                [
                    'dimension' => 'Tangibles',
                    'question_text' => 'Modern equipment?',
                    'expectation' => 4,
                    'perception' => 5,
                ],
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'Invalid or expired feedback tracking token.',
        ]);
    }

    #[Test]
    public function submit_missing_fields_returns_validation_errors(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('feedbacks.submit'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tracking_token', 'servqual_responses']);
    }

    #[Test]
    public function submit_unauthenticated_returns_302(): void
    {
        $response = $this->post(route('feedbacks.submit'), []);

        $response->assertStatus(302);
        $response->assertRedirectContains('login');
    }

    #[Test]
    public function servqual_config_returns_default_questions_json(): void
    {
        // Direct controller call due to route conflict:
        // GET /feedbacks/{feedback} shadows GET /feedbacks/servqual-config
        $request = Request::create('/feedbacks/servqual-config', 'GET');
        $request->setUserResolver(fn () => $this->user);

        $controller = app(FeedbackController::class);
        $response = $controller->servqualConfig($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('questions', $data);
    }

    #[Test]
    public function export_excel_returns_downloadable_xlsx(): void
    {
        // Skip on non-PostgreSQL — DataExportQueries uses ::numeric cast syntax
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'Export query uses PostgreSQL-specific syntax (perception::numeric).'
            );
        }

        // Direct controller call due to route conflict:
        // GET /feedbacks/{feedback} shadows GET /feedbacks/export-excel
        $request = Request::create('/feedbacks/export-excel', 'GET');
        $request->setUserResolver(fn () => $this->user);

        $controller = app(FeedbackController::class);
        $response = $controller->exportExcel($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }
}
