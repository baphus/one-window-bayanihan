<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedbackSubmitRequest;
use App\Models\Feedback;
use App\Services\Export\ColumnMaps;
use App\Services\Export\DataExportQueries;
use App\Services\Export\DataExportService;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Feedback::with(['agency', 'caseFile.client', 'servqualResponses']);

        if ($user->role === 'AGENCY' && $user->agcy_id) {
            $query->where('agency_id', $user->agcy_id);
        }

        $feedbacks = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Feedback/Index', [
            'feedbacks' => $feedbacks,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $feedback = $this->feedbackService->getFeedbackDetail($id);

        if (! $feedback) {
            abort(404, 'Feedback not found');
        }

        $user = $request->user();
        if (! $user->isAdmin()) {
            if ($feedback->caseFile) {
                $hasAccess = false;
                if ($user->isCaseManager()) {
                    $hasAccess = true;
                } elseif ($user->agcy_id) {
                    $hasAccess = $feedback->caseFile->referrals()
                        ->where('agcy_id', $user->agcy_id)
                        ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
                        ->exists();
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
        $filters = $request->only(['agency_id', 'date_from', 'date_to']);

        $queries = new DataExportQueries;
        $rows = $queries->getFeedbackWithServqual($user, $filters);
        $columnMap = ColumnMaps::getMap('feedback');
        $filename = 'feedback-export-'.now()->format('Ymd-His').'.xlsx';

        return (new DataExportService)->generateSingleSheet('Feedbacks', $columnMap, $rows, $filename);
    }
}
