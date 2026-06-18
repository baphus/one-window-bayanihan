<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Client;
use App\Models\Feedback;
use App\Models\Referral;
use App\Services\FeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeedbackService::class);
    }

    #[Test]
    public function complete_feedback_flow_creates_records_and_returns_detail(): void
    {
        // ── 1. Create the domain models ──────────────────────────────────────
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Medical Assistance',
            'status' => 'COMPLETED',
        ]);

        // ── 2. Create a CaseNotification (simulating SendFeedbackRequest) ────
        $token = (string) Str::uuid();
        CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'feedback_request',
            'title' => 'We Value Your Feedback',
            'message' => 'Your referral has been completed.',
            'data' => [
                'tracking_token' => $token,
                'referral_id' => $referral->id,
                'agency_id' => $agency->id,
                'service_name' => 'Medical Assistance',
            ],
        ]);

        // ── 3. Submit feedback via FeedbackService ──────────────────────────
        $servqualResponses = [
            [
                'dimension' => 'Tangibles',
                'question_id' => 'q1',
                'question_text' => 'The agency had modern-looking equipment.',
                'expectation' => 4,
                'perception' => 5,
            ],
            [
                'dimension' => 'Reliability',
                'question_id' => 'q2',
                'question_text' => 'The agency performed the service right the first time.',
                'expectation' => 3,
                'perception' => 4,
            ],
        ];

        $feedback = $this->service->submitFeedback(
            trackingToken: $token,
            servqualResponses: $servqualResponses,
            overallRating: 5,
            comments: 'Very satisfied with the service.',
        );

        // ── 4. Assert Feedback record created ───────────────────────────────
        $this->assertInstanceOf(Feedback::class, $feedback);
        $this->assertDatabaseHas('feedback', [
            'id' => $feedback->id,
            'case_id' => $case->id,
            'agency_id' => $agency->id,
            'referral_id' => $referral->id,
            'service_name' => 'Medical Assistance',
            'overall_rating' => 5,
            'comments' => 'Very satisfied with the service.',
        ]);

        // ── 5. Assert SERVQUAL responses created ────────────────────────────
        $this->assertDatabaseHas('feedback_servqual_responses', [
            'feedback_id' => $feedback->id,
            'dimension' => 'Tangibles',
            'question_id' => 'q1',
            'expectation' => 4,
            'perception' => 5,
        ]);
        $this->assertDatabaseHas('feedback_servqual_responses', [
            'feedback_id' => $feedback->id,
            'dimension' => 'Reliability',
            'question_id' => 'q2',
            'expectation' => 3,
            'perception' => 4,
        ]);

        // ── 6. Assert getFeedbackDetail() returns all relations ──────────────
        $detail = $this->service->getFeedbackDetail($feedback->id);
        $this->assertNotNull($detail);
        $this->assertEquals($feedback->id, $detail->id);
        $this->assertTrue($detail->relationLoaded('servqualResponses'));
        $this->assertTrue($detail->relationLoaded('caseFile'));
        $this->assertTrue($detail->relationLoaded('agency'));
        $this->assertTrue($detail->relationLoaded('referral'));
        $this->assertCount(2, $detail->servqualResponses);

        // ── 7. Assert dimension averages ────────────────────────────────────
        $averages = $this->service->getDimensionAverages($detail->servqualResponses);
        $this->assertArrayHasKey('tangibles_avg', $averages);
        $this->assertArrayHasKey('reliability_avg', $averages);
        $this->assertArrayHasKey('responsiveness_avg', $averages);
        $this->assertArrayHasKey('assurance_avg', $averages);
        $this->assertArrayHasKey('empathy_avg', $averages);
        $this->assertEquals(5.0, $averages['tangibles_avg']);
        $this->assertEquals(4.0, $averages['reliability_avg']);
        $this->assertNull($averages['responsiveness_avg']);
        $this->assertNull($averages['assurance_avg']);
        $this->assertNull($averages['empathy_avg']);
    }

    #[Test]
    public function dimension_averages_are_computed_correctly(): void
    {
        // Create models
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'required_services' => 'Legal Assistance',
            'status' => 'COMPLETED',
        ]);

        $token = (string) Str::uuid();
        CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'feedback_request',
            'title' => 'Feedback Request',
            'message' => 'Please provide your feedback.',
            'data' => ['tracking_token' => $token, 'referral_id' => $referral->id],
        ]);

        // Submit 6 responses across 3 dimensions
        $servqualResponses = [
            // Tangibles (3 responses): avg perception = (5 + 3 + 4) / 3 = 4.0
            ['dimension' => 'Tangibles', 'question_id' => 'q1', 'question_text' => 'Modern equipment', 'expectation' => 4, 'perception' => 5],
            ['dimension' => 'Tangibles', 'question_id' => 'q2', 'question_text' => 'Visually appealing', 'expectation' => 3, 'perception' => 3],
            ['dimension' => 'Tangibles', 'question_id' => 'q3', 'question_text' => 'Clean facilities', 'expectation' => 5, 'perception' => 4],
            // Reliability (2 responses): avg perception = (4 + 5) / 2 = 4.5
            ['dimension' => 'Reliability', 'question_id' => 'q4', 'question_text' => 'On-time service', 'expectation' => 4, 'perception' => 4],
            ['dimension' => 'Reliability', 'question_id' => 'q5', 'question_text' => 'Accurate records', 'expectation' => 5, 'perception' => 5],
            // Responsiveness (1 response): avg perception = 3.0
            ['dimension' => 'Responsiveness', 'question_id' => 'q6', 'question_text' => 'Prompt response', 'expectation' => 3, 'perception' => 3],
        ];

        $feedback = $this->service->submitFeedback(
            trackingToken: $token,
            servqualResponses: $servqualResponses,
            overallRating: 4,
            comments: 'Good overall.',
        );

        // Assert all 6 responses created
        $this->assertCount(6, $feedback->servqualResponses);

        // Verify dimension averages
        $averages = $this->service->getDimensionAverages($feedback->servqualResponses);

        $this->assertEquals(4.0, $averages['tangibles_avg'], 'Tangibles avg should be (5+3+4)/3 = 4.0');
        $this->assertEquals(4.5, $averages['reliability_avg'], 'Reliability avg should be (4+5)/2 = 4.5');
        $this->assertEquals(3.0, $averages['responsiveness_avg'], 'Responsiveness avg should be 3.0');
        $this->assertNull($averages['assurance_avg'], 'No Assurance responses — should be null');
        $this->assertNull($averages['empathy_avg'], 'No Empathy responses — should be null');
    }

    #[Test]
    public function submit_feedback_without_optional_fields_handles_gracefully(): void
    {
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'status' => 'COMPLETED',
        ]);

        $token = (string) Str::uuid();
        CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'feedback_request',
            'title' => 'Feedback Request',
            'message' => 'Please provide your feedback.',
            'data' => ['tracking_token' => $token, 'referral_id' => $referral->id],
        ]);

        // No overall_rating and no comments
        $feedback = $this->service->submitFeedback(
            trackingToken: $token,
            servqualResponses: [
                ['dimension' => 'Empathy', 'question_id' => 'q7', 'question_text' => 'Staff was caring', 'expectation' => 5, 'perception' => 4],
            ],
        );

        $this->assertNull($feedback->overall_rating);
        $this->assertNull($feedback->comments);
        $this->assertCount(1, $feedback->servqualResponses);
        $this->assertDatabaseHas('feedback_servqual_responses', [
            'feedback_id' => $feedback->id,
            'dimension' => 'Empathy',
            'perception' => 4,
        ]);
    }

    #[Test]
    public function feedback_list_returns_filtered_paginated_results(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);

        // Create 2 feedback for agency A, 1 for agency B
        foreach ([$agencyA, $agencyA, $agencyB] as $agcy) {
            $ref = Referral::factory()->create([
                'case_id' => $case->id,
                'agcy_id' => $agcy->id,
                'status' => 'COMPLETED',
            ]);
            $tok = (string) Str::uuid();
            CaseNotification::create([
                'case_id' => $case->id,
                'client_email' => $client->email,
                'type' => 'feedback_request',
                'title' => 'Feedback Request',
                'message' => 'Please provide your feedback.',
                'data' => ['tracking_token' => $tok, 'referral_id' => $ref->id],
            ]);
            $this->service->submitFeedback(
                trackingToken: $tok,
                servqualResponses: [
                    ['dimension' => 'Tangibles', 'question_id' => 'q1', 'question_text' => 'Q', 'expectation' => 4, 'perception' => 5],
                ],
            );
        }

        $this->assertEquals(2, $this->service->getFeedbackList(agencyId: $agencyA->id, perPage: 10)->total());
        $this->assertEquals(1, $this->service->getFeedbackList(agencyId: $agencyB->id, perPage: 10)->total());
        $this->assertEquals(3, $this->service->getFeedbackList(perPage: 10)->total());
        $this->assertCount(1, $this->service->getFeedbackList(perPage: 1));
    }

    #[Test]
    public function invalid_tracking_token_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired feedback tracking token.');

        $this->service->submitFeedback(
            trackingToken: 'nonexistent-token',
            servqualResponses: [],
        );
    }

    #[Test]
    public function question_field_fallback_works_for_servqual_responses(): void
    {
        $agency = Agency::factory()->create();
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id]);
        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agency->id,
            'status' => 'COMPLETED',
        ]);

        $token = (string) Str::uuid();
        CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $client->email,
            'type' => 'feedback_request',
            'title' => 'Feedback Request',
            'message' => 'Please provide your feedback.',
            'data' => ['tracking_token' => $token, 'referral_id' => $referral->id],
        ]);

        // Use 'question' key instead of 'question_text' — service has fallback
        $feedback = $this->service->submitFeedback(
            trackingToken: $token,
            servqualResponses: [
                [
                    'dimension' => 'Assurance',
                    'question_id' => 'q8',
                    'question' => 'Staff inspired confidence',
                    'expectation' => 4,
                    'perception' => 5,
                ],
            ],
        );

        $this->assertDatabaseHas('feedback_servqual_responses', [
            'feedback_id' => $feedback->id,
            'question_text' => 'Staff inspired confidence',
        ]);
    }
}
