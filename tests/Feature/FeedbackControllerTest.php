<?php

namespace Tests\Feature;

use App\Http\Controllers\FeedbackController;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\FeedbackServqualResponse;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
        $this->withoutVite();

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

        $response = $this->actingAs($this->user)->post(route('feedbacks.submit', ['token' => $token]), [
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
        $response = $this->actingAs($this->user)->postJson(route('feedbacks.submit', ['token' => 'non-existent-token']), [
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
            'message' => 'Unable to submit feedback at this time.',
        ]);
    }

    #[Test]
    public function submit_missing_fields_returns_validation_errors(): void
    {
        $token = (string) Str::uuid();

        $response = $this->actingAs($this->user)->postJson(route('feedbacks.submit', ['token' => $token]), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['servqual_responses']);
    }

    #[Test]
    public function submit_unauthenticated_returns_validation_error(): void
    {
        $response = $this->postJson(route('feedbacks.submit', ['token' => 'test-token']), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['servqual_responses']);
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
        // PostgreSQL-specific cast syntax (perception::numeric)
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

    #[Test]
    public function dashboard_endpoint_renders_feedback_dashboard_with_stats(): void
    {
        FeedbackInvitation::factory()->create(['agency_id' => $this->agency->id, 'case_id' => $this->case->id, 'referral_id' => $this->referral->id]);
        Feedback::create([
            'case_id' => $this->case->id,
            'agency_id' => $this->agency->id,
            'referral_id' => $this->referral->id,
            'service_name' => 'Medical Assistance',
            'overall_rating' => 5,
            'comments' => 'Great',
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $this->agency->id]))
            ->get(route('feedbacks.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Feedback/Dashboard')
                ->where('stats.total_sent', 1)
                ->where('stats.total_submitted', 1)
                ->where('window', 'all')
            );
    }

    #[Test]
    public function dashboard_applies_time_window_filter(): void
    {
        FeedbackInvitation::factory()->create(['agency_id' => $this->agency->id, 'case_id' => $this->case->id, 'referral_id' => $this->referral->id, 'created_at' => now()->subDays(10)]);
        $recentCase = CaseFile::factory()->create(['client_id' => $this->client->id]);
        $recentReferral = Referral::factory()->create(['case_id' => $recentCase->id, 'agcy_id' => $this->agency->id]);
        FeedbackInvitation::factory()->create(['agency_id' => $this->agency->id, 'case_id' => $recentCase->id, 'referral_id' => $recentReferral->id, 'created_at' => now()->subDays(2)]);
        Feedback::create([
            'case_id' => $this->case->id,
            'agency_id' => $this->agency->id,
            'referral_id' => $this->referral->id,
            'service_name' => 'Medical Assistance',
            'overall_rating' => 4,
            'comments' => 'Recent',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $this->agency->id]))
            ->get(route('feedbacks.index', ['window' => '7d']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Feedback/Dashboard')
                ->where('stats.total_sent', 1)
                ->where('stats.total_submitted', 1)
                ->where('window', '7d')
            );
    }

    #[Test]
    public function admin_dashboard_endpoint_renders_admin_feedback_dashboard(): void
    {
        $feedback = Feedback::create([
            'case_id' => $this->case->id,
            'agency_id' => $this->agency->id,
            'referral_id' => $this->referral->id,
            'service_name' => 'Medical Assistance',
            'overall_rating' => 5,
            'comments' => 'Excellent',
        ]);
        FeedbackServqualResponse::create([
            'feedback_id' => $feedback->id,
            'question_id' => 'q1',
            'question_text' => 'Helpful?',
            'dimension' => 'Empathy',
            'expectation' => 4,
            'perception' => 5,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.feedbacks.dashboard'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Feedback/AdminDashboard')
                ->has('agencySummary')
                ->has('feedbacks.data', 1)
                ->where('filters.window', 'all')
            );
    }
}
