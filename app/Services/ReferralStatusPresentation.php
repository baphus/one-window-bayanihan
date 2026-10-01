<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Referral;

/**
 * Single server-side home for referral-status display metadata.
 *
 * Four English renderings of the same five states used to live in parallel
 * (TrackingService weights/steps, CaseSwimlaneService labels, DashboardService
 * labels/tones, plus the frontend REFERRAL_STAMP). Every wording below is
 * picked verbatim from one of those existing variants — documented per
 * method — so centralizing them changes no browser-visible output. Do not
 * invent new copy here; the client-safe plain-language forms already proven
 * on the public/OFW surfaces win wherever a choice exists.
 */
final class ReferralStatusPresentation
{
    /**
     * Terminal referral states: no outgoing transition exists for them in
     * ReferralService::STATUS_TRANSITIONS.
     *
     * @var list<string>
     */
    private const TERMINAL_STATUSES = ['COMPLETED', 'REJECTED'];

    /**
     * Whether the state closes its lane. Single home for the terminal set
     * previously duplicated in CaseSwimlaneService::TERMINAL_STATUSES.
     */
    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Client-safe plain-language label. Wording taken from the REFERRAL_STAMP
     * map in resources/js/Pages/Tracking/Show.jsx (proven on public /track
     * and the OFW portal); the defensive default mirrors the previous
     * client-swimlane fallback without leaking the raw code.
     */
    public static function clientLabel(string $status): string
    {
        return match ($status) {
            'PENDING' => 'Awaiting receipt',
            'PROCESSING' => 'In process',
            'FOR_COMPLIANCE' => 'Needs documents',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Unable to assist',
            default => 'Update available',
        };
    }

    /**
     * Staff label. Preserved verbatim from CaseSwimlaneService::statusLabel
     * (which mirrors the private Referral::getStatusDescription()),
     * including the rejection-reason suffix — staff-only, never client-safe.
     */
    public static function staffLabel(string $status, ?Referral $referral = null): string
    {
        return match ($status) {
            'PENDING' => 'Sent to agency — awaiting response',
            'PROCESSING' => 'Accepted — now processing',
            'FOR_COMPLIANCE' => 'Set as For Compliance',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Rejected'
                .($referral?->rejection_reason ? ' ('.($referral->rejectionReasonLabel() ?? $referral->rejection_reason).')' : '')
                .($referral?->decision_comment ? ': '.$referral->decision_comment : ''),
            default => 'Status updated to '.$status,
        };
    }

    /**
     * Dashboard tile label. Preserved verbatim from
     * DashboardService::statusLabel (staff dashboard tiles).
     */
    public static function dashboardLabel(string $status): string
    {
        return match ($status) {
            'PENDING' => 'Pending',
            'PROCESSING' => 'Processing',
            'FOR_COMPLIANCE' => 'For compliance',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Rejected',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    /**
     * Dashboard tile tone. Preserved verbatim from
     * DashboardService::statusTone (staff dashboard tiles).
     */
    public static function tone(string $status): string
    {
        return match ($status) {
            'PENDING' => 'amber',
            'PROCESSING' => 'blue',
            'FOR_COMPLIANCE' => 'orange',
            'COMPLETED' => 'emerald',
            'REJECTED' => 'rose',
            default => 'slate',
        };
    }

    /**
     * Completion weight for the overall percentage. Preserved verbatim from
     * the TrackingService math (rejection is an outcome, not progress).
     */
    public static function weight(string $status): int
    {
        return match ($status) {
            'COMPLETED' => 100,
            'PROCESSING' => 66,
            'FOR_COMPLIANCE' => 33,
            'PENDING' => 10,
            default => 0,
        };
    }

    /**
     * Per-agency progress stepper. Moved verbatim from
     * TrackingService::buildAgencySteps (step names, not status labels, so
     * the structure is preserved exactly — only the home changed).
     *
     * @return list<array{label: string, state: string}>
     */
    public static function agencySteps(string $status, string $agencyDisplayName): array
    {
        $steps = [];

        // Step 1: Created — always complete
        $steps[] = ['label' => 'Created', 'state' => 'complete'];

        // Step 2: Referred to {agency} — always complete
        $steps[] = ['label' => "Referred to {$agencyDisplayName}", 'state' => 'complete'];

        // Step 3: Received by {agency}
        if ($status === 'PENDING') {
            $steps[] = ['label' => "Received by {$agencyDisplayName}", 'state' => 'active'];

            return $steps;
        }
        $steps[] = ['label' => "Received by {$agencyDisplayName}", 'state' => 'complete'];

        if ($status === 'REJECTED') {
            return $steps;
        }

        if ($status === 'PROCESSING') {
            $steps[] = ['label' => 'Processing', 'state' => 'active'];
            $steps[] = ['label' => 'Completed', 'state' => 'pending'];
        } elseif ($status === 'COMPLETED') {
            $steps[] = ['label' => 'Processing', 'state' => 'complete'];
            $steps[] = ['label' => 'Completed', 'state' => 'active'];
        } else {
            $steps[] = ['label' => 'Processing', 'state' => 'pending'];
            $steps[] = ['label' => 'Completed', 'state' => 'pending'];
        }

        return $steps;
    }

    /**
     * Case-level status remap for the tracking payload. Moved verbatim from
     * TrackingService's trackedCase mapping. This is deliberately case-level
     * (OPEN/CLOSED/…), not referral-level — it lives here so the tracking
     * payload's whole status vocabulary resolves through one class.
     */
    public static function caseStatus(string $status): string
    {
        return match ($status) {
            'OPEN' => 'IN_PROGRESS',
            'CLOSED' => 'RESOLVED',
            'ARCHIVED' => 'ARCHIVED',
            'DRAFT' => 'BEING_PREPARED',
            default => 'UNKNOWN',
        };
    }
}
