<?php

namespace App\Http\Controllers;

use App\Models\SurveyInvitation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SurveyResponseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $this->validateAgencyFilter($request);

        $query = $this->scopedInvitations($request);

        $requestedPerPage = filter_var($request->input('per_page', 15), FILTER_VALIDATE_INT);
        $perPage = $requestedPerPage !== false && $requestedPerPage >= 1 && $requestedPerPage <= 100
            ? $requestedPerPage
            : 15;

        $totalSent = (clone $query)->count();
        $totalSubmitted = (clone $query)->submitted()->count();
        $responseRate = $totalSent > 0 ? round(($totalSubmitted / $totalSent) * 100, 1) : 0;

        $paginationQuery = ['per_page' => $perPage];
        if ($user->role === 'ADMIN' && $request->filled('agency_id')) {
            $paginationQuery['agency_id'] = $request->input('agency_id');
        }

        $invitations = $query->submitted()
            ->with(['surveyForm', 'agency'])
            ->orderByDesc('submitted_at')
            ->paginate($perPage)
            ->appends($paginationQuery);

        $invitations->setCollection($invitations->getCollection()->map(
            fn (SurveyInvitation $invitation) => $this->serializeListInvitation($invitation)
        ));

        $filters = ['per_page' => $perPage];
        if ($user->role === 'ADMIN' && $request->filled('agency_id')) {
            $filters['agency_id'] = $request->input('agency_id');
        }

        return Inertia::render('Survey/ResponseIndex', [
            'invitations' => $invitations,
            'stats' => [
                'total_sent' => $totalSent,
                'total_submitted' => $totalSubmitted,
                'response_rate' => $responseRate,
            ],
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, SurveyInvitation $invitation)
    {
        $this->validateAgencyFilter($request);

        if (! $invitation->isSubmitted() || ! $this->scopedInvitations($request)->whereKey($invitation->id)->exists()) {
            abort(403, 'You do not have access to this survey response.');
        }

        $invitation->load(['responses.question', 'surveyForm', 'agency']);

        return Inertia::render('Survey/ResponseShow', [
            'invitation' => $this->serializeDetailInvitation($invitation),
        ]);
    }

    private function scopedInvitations(Request $request)
    {
        $user = $request->user();

        return match ($user->role) {
            'AGENCY' => SurveyInvitation::query()->where('agency_id', $user->agcy_id),
            'CASE_MANAGER' => SurveyInvitation::query(),
            'ADMIN' => SurveyInvitation::query()->when(
                $request->filled('agency_id'),
                fn ($query) => $query->where('agency_id', $request->input('agency_id')),
            ),
            default => abort(403, 'You do not have access to survey responses.'),
        };
    }

    private function validateAgencyFilter(Request $request): void
    {
        if ($request->user()->role === 'ADMIN') {
            $request->validate([
                'agency_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('agencies', 'id')],
            ]);
        }
    }

    private function serializeListInvitation(SurveyInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'client_name' => $invitation->client_name,
            'service_name' => $invitation->service_name,
            'submitted_at' => $invitation->submitted_at,
            'survey_form' => $this->serializeSurveyForm($invitation),
            'agency' => $this->serializeAgency($invitation),
        ];
    }

    private function serializeDetailInvitation(SurveyInvitation $invitation): array
    {
        return [
            'client_name' => $invitation->client_name,
            'service_name' => $invitation->service_name,
            'submitted_at' => $invitation->submitted_at,
            'survey_form' => $this->serializeSurveyForm($invitation),
            'agency' => $this->serializeAgency($invitation),
            'responses' => $invitation->responses->map(fn ($response) => [
                'id' => $response->id,
                'answer' => $response->answer,
                'selected_options' => $response->selected_options,
                'question' => $response->question ? $this->serializeQuestion($response->question) : null,
            ])->values()->all(),
        ];
    }

    private function serializeSurveyForm(SurveyInvitation $invitation): ?array
    {
        return $invitation->surveyForm ? ['title' => $invitation->surveyForm->title] : null;
    }

    private function serializeAgency(SurveyInvitation $invitation): ?array
    {
        return $invitation->agency ? ['name' => $invitation->agency->name] : null;
    }

    private function serializeQuestion($question): array
    {
        return [
            'label' => $question->label,
            'type' => $question->type,
            'order' => $question->order,
            'is_required' => $question->is_required,
        ];
    }
}
