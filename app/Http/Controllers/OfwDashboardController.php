<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\Referral;
use App\Services\IntakeDocumentService;
use App\Services\NotificationService;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class OfwDashboardController extends Controller
{
    public function __construct(
        private readonly TrackingService $trackingService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Show the OFW's case list.
     */
    public function index()
    {
        $user = request()->user();

        $cases = CaseFile::where('ofw_user_id', $user->id)
            ->where('is_deleted', false)
            ->whereIn('status', [
                CaseFile::STATUS_PENDING_REVIEW,
                'REJECTED',
                'OPEN',
                'CLOSED',
                'ARCHIVED',
            ])
            ->with(['referrals.category', 'referrals.agency'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Overview counts across all of the OFW's cases (not just the current
        // page) so the dashboard stat cards stay accurate under pagination.
        //
        // Bucketed by the canonical case statuses (case_statuses type=case plus
        // the intake/archive lifecycle states used by CaseService::updateCase /
        // getCaseStats): PENDING_REVIEW, OPEN, CLOSED, ARCHIVED. clientVisible()
        // excludes DRAFT, and DRAFT was the only status with no bucket — the
        // legacy PENDING/PROCESSING/IN_PROGRESS/BEING_PREPARED/COMPLETED/RESOLVED
        // slugs are referral-status vocabulary and never occur on a case, so the
        // buckets always sum to the total: every visible case lands in exactly
        // one card (archived cases remain client-visible and count as completed).
        $caseStatsRow = CaseFile::where('ofw_user_id', $user->id)
            ->where('is_deleted', false)
            ->whereIn('status', [
                CaseFile::STATUS_PENDING_REVIEW,
                'REJECTED',
                'OPEN',
                'CLOSED',
                'ARCHIVED',
            ])
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where status = '".CaseFile::STATUS_PENDING_REVIEW."') as under_review")
            ->selectRaw("count(*) filter (where status = 'OPEN') as in_progress")
            ->selectRaw("count(*) filter (where status in ('CLOSED', 'ARCHIVED')) as completed")
            ->first();

        // Map to an explicit array instead of passing the Eloquent row through:
        // serializing the model would auto-include the client_type accessor
        // (and lazy-load its relation), so the payload must carry exactly the
        // four keys OFW/Dashboard.jsx reads.
        $caseStats = [
            'total' => (int) ($caseStatsRow->total ?? 0),
            'under_review' => (int) ($caseStatsRow->under_review ?? 0),
            'in_progress' => (int) ($caseStatsRow->in_progress ?? 0),
            'completed' => (int) ($caseStatsRow->completed ?? 0),
        ];

        return Inertia::render('OFW/Dashboard', [
            'cases' => $cases->through(fn (CaseFile $case) => $this->ownerCasePayload($case)),
            'caseStats' => $caseStats,
            'mfaRecoveryCodes' => request()->session()->pull('mfaRecoveryCodes'),
        ]);
    }

    /**
     * Show a specific case detail with tracking data.
     */
    public function show(string $id)
    {
        $user = request()->user();

        $case = CaseFile::with([
            'referrals.agency',
            'referrals.milestones.user',
            'referrals.category',
        ])->clientVisible()->findOrFail($id);

        // Authorization: ensure the case belongs to this OFW's client
        if ($case->ofw_user_id !== $user->id) {
            abort(403);
        }

        $trackingData = $this->trackingService->buildTrackingData($case, forOfwPortal: true);

        // Spread the tracking payload top-level, mirroring TrackController's
        // contract for Tracking/Show so OFW/CaseDetail receives trackingId,
        // trackedCase, caseOverview, milestoneTimeline, completionPercentage,
        // trackingAgencies, caseNotifications, and rejectedCount directly.
        $payload = $this->ownerCasePayload($case, withDocuments: true);

        return Inertia::render('OFW/CaseDetail', array_merge($trackingData, [
            'case' => $payload,
            'documents' => collect($payload['documents'])->map(fn ($document) => [
                'id' => $document['id'], 'name' => $document['file_name'],
                'meta' => $document['category'], 'statusLabel' => 'Received',
                'download_url' => $document['download_url'],
            ])->values(),
        ]));
    }

    private function ownerCasePayload(CaseFile $case, bool $withDocuments = false): array
    {
        $payload = $case->only([
            'id', 'case_number', 'tracker_number', 'status', 'summary', 'created_at', 'updated_at',
            'intake_date', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex',
            'email', 'contact_number', 'civil_status', 'civil_status_other', 'deployment_mode', 'employment_status',
            'address_region', 'address_province', 'address_city_municipality', 'address_barangay', 'address_street',
            'employment_employer_name', 'employment_employer_address', 'employment_position', 'employment_country',
            'employment_last_country', 'employment_last_position', 'employment_start_date', 'employment_end_date',
            'employment_date_of_arrival', 'proposed_assessments', 'proposed_assessment_other', 'return_reason',
            'requested_document_slots', 'document_request_reason',
        ]);
        $payload['age_at_intake'] = $case->age_at_intake;
        $payload['categories'] = $case->referrals->pluck('category')->filter()->unique('id')
            ->map(fn ($category) => $category->only(['id', 'name', 'color']))->values();
        if ($withDocuments) {
            $payload['documents'] = $case->documents()
                ->whereIn('category', IntakeDocumentService::SLOTS)
                ->where('is_deleted', false)->get()
                ->map(fn ($document) => [
                    'id' => $document->id, 'file_name' => $document->file_name,
                    'file_type' => $document->file_type, 'category' => $document->category, 'size' => $document->size,
                    'download_url' => Route::has('ofw.case.documents.download')
                        ? route('ofw.case.documents.download', ['case' => $case->id, 'document' => $document->id])
                        : null,
                ])->values();
        }

        return $payload;
    }

    /**
     * Agency milestone updates for one of the OFW's referrals.
     *
     * Authenticated equivalent of the public `track.milestones` route. The
     * public route requires the anonymous /track OTP session binding, which a
     * logged-in OFW never has; "View all updates" from the case detail page
     * therefore links here instead.
     */
    public function agencyMilestones(string $caseId, string $referralId)
    {
        $user = request()->user();

        $case = CaseFile::query()->clientVisible()->findOrFail($caseId);

        // Same ownership check as show(): the case must belong to this OFW's client.
        if ($case->ofw_user_id !== $user->id) {
            abort(403);
        }

        $referral = Referral::query()->whereKey($referralId)->first();

        if (! $referral || $referral->case_id !== $case->id) {
            abort(404);
        }

        return Inertia::render('Tracking/AgencyMilestones', array_merge(
            $this->trackingService->buildAgencyMilestonesData($case, $referral, forOfwPortal: true),
            ['backUrl' => route('ofw.case.show', $case->id)],
        ));
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

        $caseIds = CaseFile::where('ofw_user_id', $user->id)
            ->where('is_deleted', false)
            ->clientVisible()
            ->pluck('id');

        $notifications = CaseNotification::whereIn('case_id', $caseIds)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $notifications->getCollection()->transform(function ($n) {
            $url = $this->notificationService->ofwSafeRelatedUrl(
                $n->related_url,
                $n->data ?? [],
                (string) $n->case_id,
            );

            $path = is_string($url) ? parse_url($url, PHP_URL_PATH) : null;
            $caseUrl = $n->case_id ? route('ofw.case.show', $n->case_id) : null;
            $isTrack = is_string($path) && str_starts_with($path, '/track');

            // Keep the mapped OFW destination, except public /track links: a
            // signed-in OFW never follows through to the OTP flow — the case
            // page is always the safe fallback.
            $n->setAttribute('action_url', match (true) {
                $url === null => $caseUrl,
                $isTrack => $caseUrl,
                default => $url,
            });

            return $n;
        });

        $items = collect($notifications->items())->map(fn ($n) => [
            'id' => $n->id,
            'case_id' => $n->case_id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
            'action_url' => $n->action_url,
        ]);

        if ($request->expectsJson()) {
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
            'notifications' => $notifications->setCollection($items),
        ]);
    }

    /**
     * Mark one of the OFW's notifications as read.
     */
    public function markNotificationAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $caseIds = CaseFile::where('ofw_user_id', $user->id)
            ->where('is_deleted', false)
            ->clientVisible()
            ->pluck('id');

        $updated = CaseNotification::where('id', $id)
            ->whereIn('case_id', $caseIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        // Same invalidation the service path performs, so the cached unread
        // count never lags a mark-read.
        HandleInertiaRequests::invalidateNotificationCount($user->id);

        return response()->json(['success' => true]);
    }
}
