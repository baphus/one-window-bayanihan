<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OfwDashboardController extends Controller
{
    public function __construct(
        private readonly TrackingService $trackingService,
    ) {}

    /**
     * Show the OFW's case list.
     */
    public function index()
    {
        $user = request()->user();

        $cases = CaseFile::where('client_id', $user->client_id)
            ->where('is_deleted', false)
            ->clientVisible()
            ->with(['categories', 'referrals.agency'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Overview counts across all of the OFW's cases (not just the current
        // page) so the dashboard stat cards stay accurate under pagination.
        $caseStats = CaseFile::where('client_id', $user->client_id)
            ->where('is_deleted', false)
            ->clientVisible()
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where status = 'DRAFT' and source = '".CaseFile::SOURCE_SELF_FILED."') as under_review")
            ->selectRaw("count(*) filter (where status in ('OPEN', 'PENDING', 'PROCESSING', 'FOR_COMPLIANCE', 'IN_PROGRESS', 'BEING_PREPARED')) as in_progress")
            ->selectRaw("count(*) filter (where status in ('CLOSED', 'COMPLETED', 'RESOLVED')) as completed")
            ->first();

        return Inertia::render('OFW/Dashboard', [
            'cases' => $cases,
            'caseStats' => $caseStats,
        ]);
    }

    /**
     * Show a specific case detail with tracking data.
     */
    public function show(string $id)
    {
        $user = request()->user();

        $case = CaseFile::with([
            'client.addresses',
            'client.employments',
            'client.nextOfKin',
            'referrals.agency',
            'referrals.milestones.user',
            'category',
        ])->clientVisible()->findOrFail($id);

        // Authorization: ensure the case belongs to this OFW's client
        if ($case->client_id !== $user->client_id) {
            abort(403);
        }

        $trackingData = $this->trackingService->buildTrackingData($case);

        // Spread the tracking payload top-level, mirroring TrackController's
        // contract for Tracking/Show so OFW/CaseDetail receives trackingId,
        // trackedCase, caseOverview, milestoneTimeline, completionPercentage,
        // trackingAgencies, caseNotifications, and rejectedCount directly.
        return Inertia::render('OFW/CaseDetail', array_merge($trackingData, [
            'case' => $case,
        ]));
    }

    /**
     * Show the OFW's notification inbox.
     *
     * Serves the full page (Inertia) or a JSON payload for the header bell
     * dropdown when the request expects JSON.
     */
    public function notifications(Request $request)
    {
        $user = $request->user();

        $caseIds = CaseFile::where('client_id', $user->client_id)
            ->where('is_deleted', false)
            ->clientVisible()
            ->pluck('id');

        $notifications = CaseNotification::whereIn('case_id', $caseIds)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        if ($request->expectsJson()) {
            $items = collect($notifications->items())->map(fn ($n) => [
                'id' => $n->id,
                'case_id' => $n->case_id,
                'title' => $n->title,
                'message' => $n->message,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
                'action_url' => $n->case_id ? route('ofw.case.show', $n->case_id) : null,
            ]);

            return response()->json([
                'data' => $items,
                'meta' => [
                    'total' => $notifications->total(),
                    'unread' => CaseNotification::whereIn('case_id', $caseIds)
                        ->whereNull('read_at')
                        ->count(),
                ],
            ]);
        }

        return Inertia::render('OFW/Notifications', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Mark one of the OFW's notifications as read.
     */
    public function markNotificationAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $caseIds = CaseFile::where('client_id', $user->client_id)
            ->where('is_deleted', false)
            ->clientVisible()
            ->pluck('id');

        $updated = CaseNotification::where('id', $id)
            ->whereIn('case_id', $caseIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => $updated > 0]);
    }
}
