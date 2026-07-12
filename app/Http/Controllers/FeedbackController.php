<?php

namespace App\Http\Controllers;

use App\Http\Middleware\IpWhitelist;
use App\Http\Requests\FeedbackSubmitRequest;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\Service;
use App\Services\Export\ColumnMaps;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {}

    public function dashboard(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->role === 'ADMIN') {
            $this->enforceAdminIpWhitelist($request);

            return redirect()->route('admin.feedbacks.dashboard', $request->query());
        }

        $window = $request->get('window', 'all');
        $isAgency = $user->role === 'AGENCY' && $user->agcy_id;
        $agencyId = $isAgency ? $user->agcy_id : null;

        // Time window filter
        $query = function ($query, string $createdAtColumn = 'created_at') use ($window) {
            if ($window !== 'all') {
                $from = match ($window) {
                    '7d' => now()->subDays(7),
                    '30d' => now()->subDays(30),
                    '90d' => now()->subDays(90),
                    'quarter' => now()->startOfQuarter(),
                    'year' => now()->startOfYear(),
                    default => null,
                };
                if ($from) {
                    $query->where($createdAtColumn, '>=', $from);
                }
            }
        };

        // Total invitations sent
        $invitationQuery = FeedbackInvitation::query();
        if ($agencyId) {
            $invitationQuery->where('agency_id', $agencyId);
        }
        $query($invitationQuery);
        $totalSent = $invitationQuery->count();

        // Total feedback submitted
        $feedbackQuery = Feedback::query();
        if ($agencyId) {
            $feedbackQuery->where('agency_id', $agencyId);
        }
        $query($feedbackQuery);
        $totalSubmitted = $feedbackQuery->count();

        // Response rate
        $responseRate = $totalSent > 0 ? round(($totalSubmitted / $totalSent) * 100, 1) : 0;

        // Average rating
        $ratingQuery = Feedback::query()->whereNotNull('overall_rating');
        if ($agencyId) {
            $ratingQuery->where('agency_id', $agencyId);
        }
        $query($ratingQuery);
        $avgRating = $ratingQuery->avg('overall_rating');
        $avgRating = $avgRating ? round((float) $avgRating, 2) : null;

        // Average SERVQUAL perception
        $servqualQuery = DB::table('feedback_servqual_responses')
            ->join('feedback', 'feedback_servqual_responses.feedback_id', '=', 'feedback.id')
            ->whereNotNull('feedback_servqual_responses.perception');
        if ($agencyId) {
            $servqualQuery->where('feedback.agency_id', $agencyId);
        }
        $query($servqualQuery, 'feedback.created_at');
        $avgServqual = $servqualQuery->avg('feedback_servqual_responses.perception');
        $avgServqual = $avgServqual ? round((float) $avgServqual, 2) : null;

        // Rating distribution
        $ratingDistQuery = Feedback::query()->whereNotNull('overall_rating');
        if ($agencyId) {
            $ratingDistQuery->where('agency_id', $agencyId);
        }
        $query($ratingDistQuery);
        $ratingDistribution = $ratingDistQuery
            ->select('overall_rating', DB::raw('count(*) as count'))
            ->groupBy('overall_rating')
            ->pluck('count', 'overall_rating')
            ->toArray();
        // Fill missing ratings
        for ($i = 1; $i <= 5; $i++) {
            $ratingDistribution[$i] = $ratingDistribution[$i] ?? 0;
        }
        ksort($ratingDistribution);

        // SERVQUAL dimension averages
        $dimensionQuery = DB::table('feedback_servqual_responses')
            ->join('feedback', 'feedback_servqual_responses.feedback_id', '=', 'feedback.id')
            ->whereNotNull('feedback_servqual_responses.perception');
        if ($agencyId) {
            $dimensionQuery->where('feedback.agency_id', $agencyId);
        }
        $query($dimensionQuery, 'feedback.created_at');
        $dimensionAverages = $dimensionQuery
            ->select('feedback_servqual_responses.dimension', DB::raw('avg(feedback_servqual_responses.perception) as avg_perception'))
            ->groupBy('feedback_servqual_responses.dimension')
            ->pluck('avg_perception', 'dimension')
            ->map(fn ($v) => round((float) $v, 2))
            ->toArray();

        // Service breakdown
        if ($agencyId) {
            $serviceBreakdown = Service::where('agcy_id', $agencyId)
                ->where('is_deleted', false)
                ->orderBy('name')
                ->get()
                ->map(function ($service) use ($query) {
                    $sent = FeedbackInvitation::where('service_id', $service->id);
                    $submitted = Feedback::where('service_id', $service->id);
                    $query($sent);
                    $query($submitted);

                    $sentCount = $sent->count();
                    $submittedCount = $submitted->count();
                    $avgRating = (clone $submitted)->whereNotNull('overall_rating')->avg('overall_rating');

                    return [
                        'service_id' => $service->id,
                        'service_name' => $service->name,
                        'invitations_sent' => $sentCount,
                        'count' => $submittedCount,
                        'response_rate' => $sentCount > 0 ? round(($submittedCount / $sentCount) * 100, 1) : 0,
                        'avg_rating' => $avgRating ? round((float) $avgRating, 2) : null,
                    ];
                })
                ->toArray();
        } else {
            $serviceBreakdownQuery = Feedback::query()->whereNotNull('service_id');
            $query($serviceBreakdownQuery);
            $serviceBreakdown = $serviceBreakdownQuery
                ->select('service_id', DB::raw('max(service_name) as service_name'), DB::raw('count(*) as count'), DB::raw('avg(overall_rating) as avg_rating'))
                ->groupBy('service_id')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => [
                    'service_id' => $row->service_id,
                    'service_name' => $row->service_name,
                    'count' => (int) $row->count,
                    'avg_rating' => $row->avg_rating ? round((float) $row->avg_rating, 2) : null,
                ])
                ->toArray();
        }

        // Recent feedback
        $recentQuery = Feedback::with(['agency', 'caseFile.client', 'servqualResponses']);
        if ($agencyId) {
            $recentQuery->where('agency_id', $agencyId);
        }
        $query($recentQuery);
        $recentFeedback = $recentQuery
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($fb) => [
                'id' => $fb->id,
                'client_name' => $fb->caseFile?->client
                    ? trim(($fb->caseFile->client->first_name ?? '').' '.($fb->caseFile->client->last_name ?? ''))
                    : 'Anonymous',
                'agency_name' => $fb->agency?->name ?? 'N/A',
                'service_name' => $fb->service_name ?? 'N/A',
                'overall_rating' => $fb->overall_rating,
                'comments' => $fb->comments,
                'created_at' => $fb->created_at,
                'servqual_avg' => $fb->servqualResponses->count() > 0
                    ? round($fb->servqualResponses->avg('perception'), 2)
                    : null,
            ])
            ->toArray();

        return Inertia::render('Feedback/Dashboard', [
            'stats' => [
                'total_sent' => $totalSent,
                'total_submitted' => $totalSubmitted,
                'response_rate' => $responseRate,
                'avg_rating' => $avgRating,
                'avg_servqual' => $avgServqual,
            ],
            'rating_distribution' => $ratingDistribution,
            'dimension_averages' => $dimensionAverages,
            'service_breakdown' => $serviceBreakdown,
            'recent_feedback' => $recentFeedback,
            'window' => $window,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $feedback = $this->feedbackService->getFeedbackDetail($id);

        if (! $feedback) {
            abort(404, 'Feedback not found');
        }

        $user = $request->user();
        $this->enforceAdminIpWhitelist($request);

        if (! $user->isAdmin()) {
            if ($feedback->caseFile) {
                $hasAccess = false;
                if ($user->isCaseManager()) {
                    $hasAccess = true;
                } elseif ($user->agcy_id) {
                    $hasAccess = $feedback->agency_id === $user->agcy_id;
                }
                if (! $hasAccess) {
                    abort(404, 'Feedback not found');
                }
            } elseif (! $user->isCaseManager()) {
                abort(404, 'Feedback not found');
            }
        }

        $data = $feedback->toArray();
        $data['client_name'] = $feedback->caseFile?->client
            ? trim(($feedback->caseFile->client->first_name ?? '').' '.($feedback->caseFile->client->last_name ?? ''))
            : 'Anonymous';
        $data['agency_name'] = $feedback->agency?->name ?? 'N/A';
        $data['dimension_averages'] = $this->feedbackService->getDimensionAverages(
            $feedback->servqualResponses
        );

        return Inertia::render('Feedback/Show', [
            'feedback' => $data,
        ]);
    }

    public function submitPage(Request $request): Response
    {
        $request->validate([
            'tracking_token' => 'required|string',
            'case_id' => 'nullable|string',
            'agency_id' => 'nullable|string',
            'referral_id' => 'nullable|string',
            'service_name' => 'nullable|string',
        ]);

        $questions = $this->feedbackService->getServqualConfig(
            agencyId: $request->get('agency_id'),
        );

        return Inertia::render('Feedback/Submit', [
            'case_id' => $request->get('case_id'),
            'agency_id' => $request->get('agency_id'),
            'referral_id' => $request->get('referral_id'),
            'service_name' => $request->get('service_name'),
            'questions' => $questions,
        ]);
    }

    public function submit(FeedbackSubmitRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $feedback = $this->feedbackService->submitFeedback(
                trackingToken: $validated['tracking_token'],
                servqualResponses: $validated['servqual_responses'],
                overallRating: $validated['overall_rating'] ?? null,
                comments: $validated['comments'] ?? null,
            );

            return response()->json([
                'message' => 'Feedback submitted successfully',
                'feedback_id' => $feedback->id,
            ], 201);
        } catch (\RuntimeException $e) {
            Log::error('Feedback submission failed', ['message' => $e->getMessage(), 'exception' => $e]);

            return response()->json([
                'message' => 'Unable to submit feedback at this time.',
            ], 400);
        }
    }

    public function servqualConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agency_id' => 'nullable|string|exists:agencies,id',
        ]);

        $questions = $this->feedbackService->getServqualConfig(
            agencyId: $validated['agency_id'] ?? null,
        );

        return response()->json([
            'questions' => $questions,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();
        $this->enforceAdminIpWhitelist($request);

        $filters = $request->only(['agency_id', 'date_from', 'date_to', 'window']);

        $queries = new DataExportQueries;
        $rows = $queries->getFeedbackWithServqual($user, $filters);
        $columnMap = ColumnMaps::getMap('feedback');
        $filename = 'feedback-export-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateSingleSheet('Feedbacks', $columnMap, $rows, $filename);
    }

    private function enforceAdminIpWhitelist(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            return;
        }

        app(IpWhitelist::class)->handle(
            $request,
            fn (Request $request) => response()->noContent()
        );
    }
}
