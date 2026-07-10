<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFeedbackController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $validated = $request->validate([
            'agency_id' => 'nullable|uuid|exists:agencies,id',
            'service_id' => 'nullable|uuid|exists:services,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'min_rating' => 'nullable|integer|min:1|max:5',
            'window' => 'nullable|string|in:all,7d,30d,90d,quarter,year',
        ]);

        $window = $validated['window'] ?? 'all';

        // All-agency summary
        $agencies = Agency::where('is_deleted', false)->orderBy('name')->get();
        $agencySummary = $agencies->map(function ($agency) use ($window) {
            $sent = FeedbackInvitation::where('agency_id', $agency->id);
            $submitted = Feedback::where('agency_id', $agency->id);
            $rating = Feedback::where('agency_id', $agency->id)->whereNotNull('overall_rating');

            $this->applyWindow($sent, $window);
            $this->applyWindow($submitted, $window);
            $this->applyWindow($rating, $window);

            $sentCount = $sent->count();
            $submittedCount = $submitted->count();
            $avgRating = $rating->avg('overall_rating');

            return [
                'id' => $agency->id,
                'name' => $agency->name,
                'total_sent' => $sentCount,
                'total_submitted' => $submittedCount,
                'response_rate' => $sentCount > 0 ? round(($submittedCount / $sentCount) * 100, 1) : 0,
                'avg_rating' => $avgRating ? round((float) $avgRating, 2) : null,
            ];
        })->toArray();

        // Detailed feedback table
        $query = Feedback::with(['agency', 'caseFile.client', 'servqualResponses']);

        if (! empty($validated['agency_id'])) {
            $query->where('feedback.agency_id', $validated['agency_id']);
        }
        if (! empty($validated['service_id'])) {
            $query->where('feedback.service_id', $validated['service_id']);
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate('feedback.created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('feedback.created_at', '<=', $validated['date_to']);
        }
        if (! empty($validated['min_rating'])) {
            $query->where('feedback.overall_rating', '>=', $validated['min_rating']);
        }

        $this->applyWindow($query, $window);

        $feedbacks = $query->orderByDesc('feedback.created_at')
            ->paginate(15)
            ->through(fn ($fb) => [
                'id' => $fb->id,
                'client_name' => $fb->caseFile?->client
                    ? trim(($fb->caseFile->client->first_name ?? '').' '.($fb->caseFile->client->last_name ?? ''))
                    : 'Anonymous',
                'agency_name' => $fb->agency?->name ?? 'N/A',
                'service_name' => $fb->service_name ?? 'N/A',
                'overall_rating' => $fb->overall_rating,
                'servqual_avg' => $fb->servqualResponses->count() > 0
                    ? round($fb->servqualResponses->avg('perception'), 2)
                    : null,
                'created_at' => $fb->created_at,
            ]);

        // Get all agencies and services for filter dropdowns
        $allAgencies = Agency::where('is_deleted', false)->orderBy('name')->get(['id', 'name']);
        $allServices = Service::where('is_deleted', false)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Feedback/AdminDashboard', [
            'agencySummary' => $agencySummary,
            'feedbacks' => $feedbacks,
            'filters' => [
                'agency_id' => $validated['agency_id'] ?? null,
                'service_id' => $validated['service_id'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'min_rating' => $validated['min_rating'] ?? null,
                'window' => $window,
            ],
            'allAgencies' => $allAgencies,
            'allServices' => $allServices,
        ]);
    }

    private function applyWindow($query, string $window): void
    {
        if ($window === 'all') {
            return;
        }

        $from = match ($window) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => null,
        };

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
    }
}
