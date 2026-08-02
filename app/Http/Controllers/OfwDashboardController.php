<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Services\TrackingService;
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

        return Inertia::render('OFW/Dashboard', [
            'cases' => $cases,
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

        return Inertia::render('OFW/CaseDetail', [
            'case' => $case,
            'trackingData' => $trackingData,
        ]);
    }

    /**
     * Show the OFW's notification inbox.
     */
    public function notifications()
    {
        $user = request()->user();

        $caseIds = CaseFile::where('client_id', $user->client_id)
            ->where('is_deleted', false)
            ->clientVisible()
            ->pluck('id');

        $notifications = CaseNotification::whereIn('case_id', $caseIds)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('OFW/Notifications', [
            'notifications' => $notifications,
        ]);
    }
}
