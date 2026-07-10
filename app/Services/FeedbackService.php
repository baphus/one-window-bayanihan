<?php

namespace App\Services;

use App\Models\CaseNotification;
use App\Models\Feedback;
use App\Models\FeedbackServqualResponse;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServqualConfig;
use App\Models\SystemSetting;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeedbackService
{
    /**
     * Generate a tracking token for a feedback request.
     *
     * The caller (event listener) uses the returned token to create a
     * CaseNotification and send the FeedbackRequestMail to the OFW.
     *
     * @param  string  $referralId  The referral being evaluated.
     * @param  string  $caseId  The case associated with the referral.
     * @param  string  $agencyId  The agency that provided the service.
     * @param  string  $ofwEmail  The OFW's email address for the notification.
     * @return string The generated tracking token.
     */
    public function requestFeedback(
        string $referralId,
        string $caseId,
        string $agencyId,
        string $ofwEmail,
    ): string {
        return Str::random(32);
    }

    /**
     * Submit feedback with SERVQUAL responses.
     *
     * Validates the tracking token by finding the corresponding CaseNotification,
     * creates the Feedback record, and stores all SERVQUAL responses.
     *
     * @param  string  $trackingToken  The token from the feedback request notification.
     * @param  array  $servqualResponses  Array of SERVQUAL response data.
     *                                    Each item: dimension, question_id, question_text,
     *                                    expectation (1-5), perception (1-5).
     * @param  int|null  $overallRating  Optional overall satisfaction rating (1-5).
     * @param  string|null  $comments  Optional free-text comments.
     * @return Feedback The created Feedback with relationships loaded.
     *
     * @throws \RuntimeException When the tracking token is invalid or expired.
     */
    public function submitFeedback(
        string $trackingToken,
        array $servqualResponses,
        ?int $overallRating = null,
        ?string $comments = null,
    ): Feedback {
        $notification = CaseNotification::where('type', 'feedback_request')
            ->where('data->tracking_token', $trackingToken)
            ->first();

        if (! $notification) {
            throw new \RuntimeException('Invalid or expired feedback tracking token.');
        }

        $referralId = $notification->data['referral_id'] ?? null;
        $caseId = $notification->case_id;

        // Derive agency_id and service_name from the referral
        $agencyId = null;
        $serviceId = $notification->data['service_id'] ?? null;
        $serviceName = '';

        if ($referralId) {
            $referral = Referral::find($referralId);
            if ($referral) {
                $agencyId = $referral->agcy_id;
                $serviceName = $referral->required_services ?? '';
                if (! $serviceId && $serviceName) {
                    $serviceId = Service::where('agcy_id', $agencyId)
                        ->where('name', $serviceName)
                        ->where('is_deleted', false)
                        ->value('id');
                }
            }
        }

        $feedback = DB::transaction(function () use ($caseId, $agencyId, $referralId, $serviceId, $serviceName, $overallRating, $comments, $servqualResponses) {
            $feedback = Feedback::create([
                'case_id' => $caseId,
                'agency_id' => $agencyId,
                'referral_id' => $referralId,
                'service_id' => $serviceId,
                'service_name' => $serviceName,
                'overall_rating' => $overallRating,
                'comments' => $comments,
            ]);

            foreach ($servqualResponses as $response) {
                FeedbackServqualResponse::create([
                    'feedback_id' => $feedback->id,
                    'dimension' => $response['dimension'] ?? '',
                    'question_id' => $response['question_id'] ?? '',
                    'question_text' => $response['question_text'] ?? ($response['question'] ?? ''),
                    'expectation' => $response['expectation'] ?? null,
                    'perception' => $response['perception'] ?? null,
                ]);
            }

            return $feedback;
        });

        return $feedback->load(['servqualResponses', 'caseFile', 'agency', 'referral']);
    }

    /**
     * Get detailed feedback with all relationships.
     *
     * @param  string  $feedbackId  The UUID of the feedback record.
     */
    public function getFeedbackDetail(string $feedbackId): ?Feedback
    {
        return Feedback::with(['servqualResponses', 'caseFile', 'agency', 'referral'])
            ->find($feedbackId);
    }

    /**
     * Get a paginated list of feedback records with optional filters.
     *
     * @param  string|null  $caseId  Filter by case UUID.
     * @param  string|null  $agencyId  Filter by agency UUID.
     * @param  int  $perPage  Items per page (default: 15).
     */
    public function getFeedbackList(
        ?string $caseId = null,
        ?string $agencyId = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Feedback::with(['caseFile', 'agency'])
            ->orderBy('created_at', 'desc');

        if ($caseId) {
            $query->where('case_id', $caseId);
        }

        if ($agencyId) {
            $query->where('agency_id', $agencyId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get the merged SERVQUAL configuration for a given agency.
     *
     * Returns default questions from system settings, overridden and extended
     * by agency-specific configuration from the servqual_configs table.
     *
     * @param  string|null  $agencyId  The agency UUID, or null for defaults only.
     * @return array Structured array of questions, each with dimension, question, order.
     */
    public function getServqualConfig(?string $agencyId = null): array
    {
        // Load default questions from system settings
        $defaultRaw = SystemSetting::getValue('default_servqual_questions', '[]');
        $defaultQuestions = is_string($defaultRaw)
            ? (json_decode($defaultRaw, true) ?? [])
            : [];

        // Without an agency, return defaults only
        if (! $agencyId) {
            return $defaultQuestions;
        }

        // Load agency-specific active config
        $agencyConfig = ServqualConfig::where('agency_id', $agencyId)
            ->where('is_active', true)
            ->first();

        if (! $agencyConfig || empty($agencyConfig->questions)) {
            return $defaultQuestions;
        }

        $agencyQuestions = $agencyConfig->questions;

        // Merge: agency overrides replace defaults where dimension+question match,
        // otherwise agency questions are appended.
        $merged = $defaultQuestions;

        foreach ($agencyQuestions as $aq) {
            $found = false;

            foreach ($merged as &$dq) {
                if (($dq['dimension'] ?? '') === ($aq['dimension'] ?? '')
                    && ($dq['question'] ?? '') === ($aq['question'] ?? '')) {
                    $dq = $aq;
                    $found = true;
                    break;
                }
            }
            unset($dq);

            if (! $found) {
                $merged[] = $aq;
            }
        }

        return $merged;
    }

    /**
     * Get dimension averages as a flat associative array for frontend display.
     *
     * Returns keys: tangibles_avg, reliability_avg, responsiveness_avg,
     *               assurance_avg, empathy_avg
     * Values are rounded to 2 decimal places, or null if no responses exist
     * for that dimension.
     *
     * @param  Collection  $servqualResponses  Collection of FeedbackServqualResponse models.
     * @return array<string, float|null>
     */
    public function getDimensionAverages(Collection $servqualResponses): array
    {
        $dimensionMap = [
            'Tangibles' => 'tangibles_avg',
            'Reliability' => 'reliability_avg',
            'Responsiveness' => 'responsiveness_avg',
            'Assurance' => 'assurance_avg',
            'Empathy' => 'empathy_avg',
        ];

        $grouped = $servqualResponses->groupBy('dimension');
        $result = [];

        foreach ($dimensionMap as $dimension => $key) {
            $responses = $grouped->get($dimension, collect());
            $avg = $responses->count() > 0
                ? round((float) $responses->avg('perception'), 2)
                : null;
            $result[$key] = $avg;
        }

        return $result;
    }

    /**
     * Get feedback data for export with dimension-average calculations.
     *
     * @param  array  $filters  Optional filters: case_id, agency_id, date_from, date_to.
     * @return Collection Collection of objects with case_ref, agency_name, service_name,
     *                    overall_rating, dimension_averages, comments, created_at.
     */
    public function getExportData(array $filters): Collection
    {
        $query = Feedback::with(['servqualResponses', 'caseFile', 'agency', 'referral']);

        if (! empty($filters['case_id'])) {
            $query->where('case_id', $filters['case_id']);
        }

        if (! empty($filters['agency_id'])) {
            $query->where('agency_id', $filters['agency_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->get()->map(function (Feedback $feedback) {
            $dimensionAverages = $this->calcDimensionAverages(
                $feedback->servqualResponses
            );

            return (object) [
                'case_ref' => $feedback->caseFile?->case_number,
                'agency_name' => $feedback->agency?->name,
                'service_name' => $feedback->service_name,
                'overall_rating' => $feedback->overall_rating,
                'dimension_averages' => $dimensionAverages,
                'comments' => $feedback->comments,
                'created_at' => $feedback->created_at,
            ];
        });
    }

    /**
     * Calculate dimension-level averages from SERVQUAL responses.
     *
     * Groups responses by dimension and computes the average expectation,
     * average perception, and the gap between them.
     *
     * @param  Collection  $servqualResponses  Collection of FeedbackServqualResponse models.
     * @return array Array of dimension-average entries.
     */
    private function calcDimensionAverages(Collection $servqualResponses): array
    {
        return $servqualResponses
            ->groupBy('dimension')
            ->map(function ($responses, string $dimension): array {
                $avgExpectation = $responses->avg('expectation');
                $avgPerception = $responses->avg('perception');

                return [
                    'dimension' => $dimension,
                    'average_expectation' => round((float) $avgExpectation, 2),
                    'average_perception' => round((float) $avgPerception, 2),
                    'gap' => round((float) $avgPerception - (float) $avgExpectation, 2),
                    'count' => $responses->count(),
                ];
            })
            ->values()
            ->toArray();
    }
}
