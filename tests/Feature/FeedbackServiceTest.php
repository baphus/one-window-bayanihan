<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\Feedback;
use App\Models\FeedbackServqualResponse;
use App\Models\Referral;
use App\Services\FeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeedbackService::class);
    }

    #[Test]
    public function request_feedback_returns_tracking_token(): void
    {
        $token = $this->service->requestFeedback(
            referralId: (string) Str::uuid(),
            caseId: (string) Str::uuid(),
            agencyId: (string) Str::uuid(),
            ofwEmail: 'test@example.com',
        );

        $this->assertEquals(32, strlen($token));
    }

    #[Test]
    public function request_feedback_returns_unique_tokens(): void
    {
        $token1 = $this->service->requestFeedback(
            referralId: (string) Str::uuid(),
            caseId: (string) Str::uuid(),
            agencyId: (string) Str::uuid(),
            ofwEmail: 'test@example.com',
        );
        $token2 = $this->service->requestFeedback(
            referralId: (string) Str::uuid(),
            caseId: (string) Str::uuid(),
            agencyId: (string) Str::uuid(),
            ofwEmail: 'test@example.com',
        );

        $this->assertNotEquals($token1, $token2);
    }

    #[Test]
    public function submit_feedback_creates_feedback_and_responses(): void
    {
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Legal Assistance',
        ]);

        $token = (string) Str::uuid();

        CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'feedback_request',
            'title' => 'Feedback Request',
            'message' => 'Please provide your feedback.',
            'data' => [
                'tracking_token' => $token,
                'referral_id' => $referral->id,
            ],
        ]);

        $servqualResponses = [
            [
                'dimension' => 'Tangibles',
                'question_id' => 'q1',
                'question' => 'The agency had modern-looking equipment.',
                'expectation' => 4,
                'perception' => 5,
            ],
            [
                'dimension' => 'Reliability',
                'question_id' => 'q2',
                'question' => 'The agency performed the service right the first time.',
                'expectation' => 3,
                'perception' => 4,
            ],
        ];

        $result = $this->service->submitFeedback(
            trackingToken: $token,
            servqualResponses: $servqualResponses,
            overallRating: 4,
            comments: 'Very satisfied with the service.',
        );

        $this->assertInstanceOf(Feedback::class, $result);
        $this->assertDatabaseHas('feedback', [
            'id' => $result->id,
            'case_id' => $case->id,
            'agency_id' => $agency->id,
            'referral_id' => $referral->id,
            'service_name' => 'Legal Assistance',
            'overall_rating' => 4,
            'comments' => 'Very satisfied with the service.',
        ]);
        $this->assertDatabaseHas('feedback_servqual_responses', [
            'feedback_id' => $result->id,
            'dimension' => 'Tangibles',
            'expectation' => 4,
            'perception' => 5,
        ]);
        $this->assertDatabaseHas('feedback_servqual_responses', [
            'feedback_id' => $result->id,
            'dimension' => 'Reliability',
            'expectation' => 3,
            'perception' => 4,
        ]);
    }

    #[Test]
    public function submit_feedback_throws_on_invalid_token(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired feedback tracking token.');

        $this->service->submitFeedback(
            trackingToken: 'nonexistent-token',
            servqualResponses: [],
        );
    }

    #[Test]
    public function get_feedback_detail_returns_feedback_with_relations(): void
    {
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
        ]);

        $feedback = Feedback::create([
            'case_id' => $case->id,
            'agency_id' => $agency->id,
            'referral_id' => $referral->id,
            'service_name' => 'Test Service',
            'overall_rating' => 5,
            'comments' => 'Excellent service!',
        ]);

        FeedbackServqualResponse::create([
            'feedback_id' => $feedback->id,
            'dimension' => 'Tangibles',
            'question_id' => 'q1',
            'question_text' => 'Modern equipment?',
            'expectation' => 4,
            'perception' => 5,
        ]);

        $result = $this->service->getFeedbackDetail($feedback->id);

        $this->assertNotNull($result);
        $this->assertEquals($feedback->id, $result->id);
        $this->assertTrue($result->relationLoaded('servqualResponses'));
        $this->assertTrue($result->relationLoaded('caseFile'));
        $this->assertTrue($result->relationLoaded('agency'));
        $this->assertTrue($result->relationLoaded('referral'));
        $this->assertCount(1, $result->servqualResponses);
        $this->assertEquals('Tangibles', $result->servqualResponses->first()->dimension);
    }

    #[Test]
    public function get_feedback_list_returns_filtered_paginated_results(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $client = Client::factory()->create();
        $caseA = CaseFile::factory()->create(['client_id' => $client->id]);
        $caseB = CaseFile::factory()->create(['client_id' => $client->id]);

        Feedback::create(['case_id' => $caseA->id, 'agency_id' => $agencyA->id]);
        Feedback::create(['case_id' => $caseA->id, 'agency_id' => $agencyA->id]);
        Feedback::create(['case_id' => $caseB->id, 'agency_id' => $agencyB->id]);

        // Filter by agencyA
        $results = $this->service->getFeedbackList(agencyId: $agencyA->id, perPage: 10);
        $this->assertEquals(2, $results->total());

        // Filter by agencyB
        $results = $this->service->getFeedbackList(agencyId: $agencyB->id, perPage: 10);
        $this->assertEquals(1, $results->total());

        // Filter by caseA
        $results = $this->service->getFeedbackList(caseId: $caseA->id, perPage: 10);
        $this->assertEquals(2, $results->total());

        // No filters — all records
        $results = $this->service->getFeedbackList(perPage: 10);
        $this->assertEquals(3, $results->total());

        // Pagination
        $results = $this->service->getFeedbackList(perPage: 1);
        $this->assertCount(1, $results);
        $this->assertEquals(1, $results->perPage());
        $this->assertEquals(3, $results->total());
    }
}
