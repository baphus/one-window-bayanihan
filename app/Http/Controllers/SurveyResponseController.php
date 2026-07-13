<?php

namespace App\Http\Controllers;

use App\Models\SurveyInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SurveyResponseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = SurveyInvitation::with(['surveyForm', 'agency']);

        if ($user->role === 'AGENCY') {
            $query->where('agency_id', $user->agcy_id);
        } elseif ($user->role === 'ADMIN' && $request->filled('agency_id')) {
            $query->where('agency_id', $request->input('agency_id'));
        }

        $totalSent = (clone $query)->count();
        $totalSubmitted = (clone $query)->submitted()->count();
        $responseRate = $totalSent > 0 ? round(($totalSubmitted / $totalSent) * 100, 1) : 0;

        $invitations = $query->submitted()
            ->orderByDesc('submitted_at')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Survey/ResponseIndex', [
            'invitations' => $invitations,
            'stats' => [
                'total_sent' => $totalSent,
                'total_submitted' => $totalSubmitted,
                'response_rate' => $responseRate,
            ],
            'filters' => $request->only(['agency_id', 'per_page']),
        ]);
    }

    public function show(Request $request, SurveyInvitation $invitation)
    {
        $user = $request->user();

        if ($user->role === 'AGENCY' && $invitation->agency_id !== $user->agcy_id) {
            abort(403, 'You do not have access to this survey response.');
        }

        $invitation->load(['responses.question', 'surveyForm', 'agency', 'caseFile']);

        return Inertia::render('Survey/ResponseShow', [
            'invitation' => $invitation,
        ]);
    }
}
