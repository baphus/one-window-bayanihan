<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\Feedback;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // All-agency summary (single GROUP BY query instead of N+1)
        $from = $this->resolveWindowDate($window);

        $agencySummary = CacheHelper::safeRemember(
            'feedback:admin_summary:' . $window,
            300,
            function () use ($from) {
                $invitationJoin = $from
                    ? 'LEFT JOIN feedback_invitations fi ON fi.agency_id = a.id AND fi.created_at >= ?'
                    : 'LEFT JOIN feedback_invitations fi ON fi.agency_id = a.id';

                $feedbackJoin = $from
                    ? 'LEFT JOIN feedback f ON f.agency_id = a.id AND f.created_at >= ?'
                    : 'LEFT JOIN feedback f ON f.agency_id = a.id';

                $sql = "
                    SELECT a.id, a.name,
                        COUNT(DISTINCT fi.id) AS total_sent,
                        COUNT(DISTINCT f.id) AS total_submitted,
                        AVG(f.overall_rating) FILTER (WHERE f.overall_rating IS NOT NULL) AS avg_rating
                    FROM agencies a
                    {$invitationJoin}
                    {$feedbackJoin}
                    WHERE a.is_deleted = false
                    GROUP BY a.id, a.name
                    ORDER BY a.name
                ";

                $bindings = $from ? [$from, $from] : [];
                $rows = DB::select($sql, $bindings);

                return array_map(fn ($row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'total_sent' => (int) $row->total_sent,
                    'total_submitted' => (int) $row->total_submitted,
                    'response_rate' => $row->total_sent > 0 ? round(($row->total_submitted / $row->total_sent) * 100, 1) : 0,
                    'avg_rating' => $row->avg_rating ? round((float) $row->avg_rating, 2) : null,
                ], $rows);
            }
        );

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

        $from = $this->resolveWindowDate($window);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
    }

    private function resolveWindowDate(string $window)
    {
        if ($window === 'all') {
            return null;
        }

        return match ($window) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => null,
        };
    }
}
