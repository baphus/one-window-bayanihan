<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicFeedbackSubmitRequest;
use App\Models\CaseNotification;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\FeedbackServqualResponse;
use App\Services\FeedbackInvitationService;
use App\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles public-facing (unauthenticated) feedback form rendering and submission.
 *
 * Supports both new FeedbackInvitation tokens and old CaseNotification tokens
 * during the transition period.
 */
class PublicFeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackInvitationService $invitationService,
        private readonly FeedbackService $feedbackService,
    ) {}

    /**
     * Show the public feedback form for a given token.
     *
     * Derives all form data from the invitation: form_snapshot, rating_labels, client name.
     * Falls back to old CaseNotification token lookup during transition.
     */
    public function showForm(Request $request, string $token): Response
    {
        // Attempt new-style FeedbackInvitation token validation first
        try {
            $invitation = $this->invitationService->validatePublicToken($token);

            $clientName = $invitation->caseFile?->client
                ? trim(($invitation->caseFile->client->first_name ?? '').' '.($invitation->caseFile->client->last_name ?? ''))
                : '';

            return Inertia::render('Feedback/Submit', [
                'invitation' => [
                    'id' => $invitation->id,
                    'token' => $token,
                    'case_id' => $invitation->case_id,
                    'agency_id' => $invitation->agency_id,
                    'referral_id' => $invitation->referral_id,
                    'service_id' => $invitation->service_id,
                    'service_name' => $invitation->service_name,
                    'form_snapshot' => $invitation->form_snapshot,
                    'rating_labels' => $invitation->rating_labels,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ],
                'client_name' => $clientName,
            ]);
        } catch (\RuntimeException $e) {
            // The invitation exists but is expired or already submitted.
            // If already submitted: the redirect from a successful POST lands
            // here (flash.success is in the session → thank-you screen).
            // If revisited manually: no flash → show "already submitted" screen.
            $rawPrefix = substr($token, 0, 10);
            $rawHash = hash('sha256', $token);
            $invitation = FeedbackInvitation::where('token_prefix', $rawPrefix)
                ->where('token_hash', $rawHash)
                ->first();

            if ($invitation && $invitation->isSubmitted()) {
                return Inertia::render('Feedback/Submit', [
                    'alreadySubmitted' => true,
                ]);
            }

            if ($invitation) {
                return Inertia::render('Feedback/Submit', [
                    'expired' => true,
                ]);
            }

            // Fall back to old CaseNotification token lookup
            return $this->renderWithOldToken($request, $token);
        }
    }

    /**
     * Submit feedback for a given token.
     *
     * Supports both new FeedbackInvitation tokens and old CaseNotification tokens.
     */
    public function submit(PublicFeedbackSubmitRequest $request, string $token): RedirectResponse
    {
        $validated = $request->validated();

        // Try new-style FeedbackInvitation token first
        try {
            $invitation = $this->invitationService->validatePublicToken($token);
        } catch (\RuntimeException $e) {
            // Fall back to old CaseNotification token
            return $this->submitWithOldToken($request, $token, $validated);
        }

        $feedback = DB::transaction(function () use ($invitation, $validated) {
            $feedback = Feedback::create([
                'case_id' => $invitation->case_id,
                'agency_id' => $invitation->agency_id,
                'referral_id' => $invitation->referral_id,
                'service_id' => $invitation->service_id,
                'service_name' => $invitation->service_name,
                'overall_rating' => $validated['overall_rating'] ?? null,
                'comments' => $validated['comments'] ?? null,
            ]);

            foreach ($validated['servqual_responses'] as $response) {
                FeedbackServqualResponse::create([
                    'feedback_id' => $feedback->id,
                    'dimension' => $response['dimension'],
                    'question_id' => $response['question_id'] ?? '',
                    'question_text' => $response['question_text'],
                    'expectation' => $response['expectation'],
                    'perception' => $response['perception'],
                ]);
            }

            $this->invitationService->markSubmitted($invitation, $feedback);

            return $feedback;
        });

        return redirect()->route('feedbacks.submit-page', $token)
            ->with('success', 'Feedback submitted successfully. Thank you!');
    }

    /**
     * Try to resolve an old CaseNotification token.
     */
    private function resolveOldToken(string $token): ?object
    {
        $notification = CaseNotification::where('type', 'feedback_request')
            ->where('data->tracking_token', $token)
            ->first();

        if (! $notification) {
            return null;
        }

        return (object) [
            'case_id' => $notification->case_id,
            'agency_id' => $notification->data['agency_id'] ?? null,
            'referral_id' => $notification->data['referral_id'] ?? null,
            'service_name' => $notification->data['service_name'] ?? '',
            'form_snapshot' => [],
            'rating_labels' => [],
            'expires_at' => null,
            'caseFile' => $notification->caseFile,
        ];
    }

    /**
     * Render feedback form using old CaseNotification data.
     * Uses live SERVQUAL config rather than snapshot.
     */
    private function renderWithOldToken(Request $request, string $token): Response
    {
        $invitation = $this->resolveOldToken($token);
        if (! $invitation) {
            abort(404, 'Invalid or expired feedback link.');
        }

        $questions = $this->feedbackService->getServqualConfig(
            agencyId: $invitation->agency_id,
        );

        $clientName = $invitation->caseFile?->client
            ? trim(($invitation->caseFile->client->first_name ?? '').' '.($invitation->caseFile->client->last_name ?? ''))
            : '';

        return Inertia::render('Feedback/Submit', [
            'tracking_token' => $token,
            'case_id' => $invitation->case_id,
            'agency_id' => $invitation->agency_id,
            'referral_id' => $invitation->referral_id,
            'service_name' => $invitation->service_name,
            'questions' => $questions,
            'client_name' => $clientName,
        ]);
    }

    /**
     * Submit feedback using old CaseNotification token.
     * Uses FeedbackService::submitFeedback which handles old-style tokens.
     */
    private function submitWithOldToken(Request $request, string $token, array $validated): RedirectResponse
    {
        try {
            $this->feedbackService->submitFeedback(
                trackingToken: $token,
                servqualResponses: $validated['servqual_responses'],
                overallRating: $validated['overall_rating'] ?? null,
                comments: $validated['comments'] ?? null,
            );

            return redirect()->route('feedbacks.submit-page', $token)
                ->with('success', 'Feedback submitted successfully. Thank you!');
        } catch (\RuntimeException $e) {
            Log::error('Public feedback submission failed', [
                'token_prefix' => substr($token, 0, 10),
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'Unable to submit feedback at this time. Please try again later.');
        }
    }
}
