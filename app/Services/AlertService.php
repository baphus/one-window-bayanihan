<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlertService
{
    /**
     * Get all active (non-dismissed) alerts for a user, with unread count.
     *
     * @return array{data: Collection, unread_count: int}
     */
    public function getActiveAlerts(User $user): array
    {
        $alerts = DB::table('alerts')
            ->where('assigned_to_id', $user->id)
            ->whereNull('dismissed_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $unreadCount = DB::table('alerts')
            ->where('assigned_to_id', $user->id)
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->count();

        return [
            'data' => $alerts,
            'unread_count' => $unreadCount,
        ];
    }

    /**
     * Run all alert condition checks and create alert records where conditions are met.
     *
     * @return array{success: bool, created: int}
     */
    public function checkAlerts(): array
    {
        $created = 0;

        $created += $this->checkCaseStalled();
        $created += $this->checkReferralStalled();
        $created += $this->checkSlaBreachWarning();
        $created += $this->checkSlaBreached();
        $created += $this->checkAgencyOverloaded();
        $created += $this->checkReferralRejected();
        $created += $this->checkNewReferral();
        $created += $this->checkFeedbackSubmitted();
        $created += $this->checkCapacityWarning();

        return ['success' => true, 'created' => $created];
    }

    // -----------------------------------------------------------------------
    //  Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Insert a single alert record into the database.
     */
    private function createAlert(
        string $type,
        string $severity,
        string $title,
        ?string $message,
        string $entityType,
        string $entityId,
        string $assignedToId
    ): void {
        DB::table('alerts')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'assigned_to_id' => $assignedToId,
            'created_at' => now(),
        ]);
    }

    /**
     * Check whether an active (non-dismissed) alert already exists for the
     * given type + entity combination.
     */
    private function hasActiveAlert(string $type, string $entityId): bool
    {
        return DB::table('alerts')
            ->where('type', $type)
            ->where('entity_id', $entityId)
            ->whereNull('dismissed_at')
            ->exists();
    }

    // -----------------------------------------------------------------------
    //  Individual check methods (one per alert type)
    // -----------------------------------------------------------------------

    /**
     * Cases with status OPEN that haven't been updated in 7+ days.
     */
    private function checkCaseStalled(): int
    {
        $cases = DB::table('cases')
            ->where('status', 'OPEN')
            ->where('is_deleted', false)
            ->where('updated_at', '<', now()->subDays(7))
            ->get();

        $created = 0;

        foreach ($cases as $case) {
            if ($this->hasActiveAlert('case_stalled', $case->id)) {
                continue;
            }

            $this->createAlert(
                'case_stalled',
                'warning',
                "Case {$case->case_number} has stalled",
                'This case has not been updated in over 7 days.',
                'case',
                $case->id,
                $case->user_id,
            );
            $created++;
        }

        return $created;
    }

    /**
     * Referrals stuck in PENDING / PROCESSING / FOR COMPLIANCE for 7+ days.
     */
    private function checkReferralStalled(): int
    {
        $referrals = DB::table('referrals')
            ->whereIn('status', ['PENDING', 'PROCESSING', 'FOR COMPLIANCE'])
            ->where('is_deleted', false)
            ->where('updated_at', '<', now()->subDays(7))
            ->get();

        $created = 0;

        foreach ($referrals as $referral) {
            if ($this->hasActiveAlert('referral_stalled', $referral->id)) {
                continue;
            }

            $caseUserId = DB::table('cases')
                ->where('id', $referral->case_id)
                ->value('user_id');

            if ($caseUserId) {
                $this->createAlert(
                    'referral_stalled',
                    'warning',
                    'Referral has stalled',
                    'A referral has not been updated in over 7 days.',
                    'referral',
                    $referral->id,
                    $caseUserId,
                );
                $created++;
            }
        }

        return $created;
    }

    /**
     * OPEN cases with a 30-day SLA that are >20 days old but <30 days old,
     * and have not yet been marked sla_met.
     */
    private function checkSlaBreachWarning(): int
    {
        $cases = DB::table('cases')
            ->where('status', 'OPEN')
            ->where('is_deleted', false)
            ->where('sla_target_days', 30)
            ->where('sla_met', false)
            ->where('created_at', '<', now()->subDays(20))
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $created = 0;

        foreach ($cases as $case) {
            if ($this->hasActiveAlert('sla_breach_warning', $case->id)) {
                continue;
            }

            $this->createAlert(
                'sla_breach_warning',
                'warning',
                "SLA breach warning for case {$case->case_number}",
                'This case is approaching its 30-day SLA deadline.',
                'case',
                $case->id,
                $case->user_id,
            );
            $created++;
        }

        return $created;
    }

    /**
     * OPEN cases with a 30-day SLA that are >30 days old and not sla_met.
     */
    private function checkSlaBreached(): int
    {
        $cases = DB::table('cases')
            ->where('status', 'OPEN')
            ->where('is_deleted', false)
            ->where('sla_target_days', 30)
            ->where('sla_met', false)
            ->where('created_at', '<', now()->subDays(30))
            ->get();

        $created = 0;

        foreach ($cases as $case) {
            if ($this->hasActiveAlert('sla_breached', $case->id)) {
                continue;
            }

            $this->createAlert(
                'sla_breached',
                'critical',
                "SLA breached for case {$case->case_number}",
                'This case has exceeded its 30-day SLA deadline.',
                'case',
                $case->id,
                $case->user_id,
            );
            $created++;
        }

        return $created;
    }

    /**
     * Agencies with more than 10 active referrals (PENDING / PROCESSING / FOR COMPLIANCE).
     * Alerts are sent to all ADMIN users.
     */
    private function checkAgencyOverloaded(): int
    {
        $overloaded = DB::table('referrals')
            ->select('agcy_id')
            ->selectRaw('COUNT(*) as referral_count')
            ->where('is_deleted', false)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'FOR COMPLIANCE'])
            ->groupBy('agcy_id')
            ->havingRaw('COUNT(*) > 10')
            ->get();

        $created = 0;
        $admins = null;

        foreach ($overloaded as $item) {
            if ($this->hasActiveAlert('agency_overloaded', $item->agcy_id)) {
                continue;
            }

            $agencyName = DB::table('agencies')
                ->where('id', $item->agcy_id)
                ->value('name') ?? 'Unknown Agency';

            if ($admins === null) {
                $admins = DB::table('users')
                    ->where('role', 'ADMIN')
                    ->where('is_deleted', false)
                    ->get();
            }

            foreach ($admins as $admin) {
                $this->createAlert(
                    'agency_overloaded',
                    'warning',
                    "Agency overloaded: {$agencyName}",
                    "This agency has {$item->referral_count} active referrals.",
                    'agency',
                    $item->agcy_id,
                    $admin->id,
                );
                $created++;
            }
        }

        return $created;
    }

    /**
     * Referrals that have been rejected. Alerts are sent to the case manager
     * who owns the parent case.
     */
    private function checkReferralRejected(): int
    {
        $referrals = DB::table('referrals')
            ->where('status', 'REJECTED')
            ->where('is_deleted', false)
            ->get();

        $created = 0;

        foreach ($referrals as $referral) {
            if ($this->hasActiveAlert('referral_rejected', $referral->id)) {
                continue;
            }

            $userId = DB::table('cases')
                ->where('id', $referral->case_id)
                ->value('user_id');

            if ($userId) {
                $this->createAlert(
                    'referral_rejected',
                    'critical',
                    'Referral rejected',
                    'A referral has been rejected.',
                    'referral',
                    $referral->id,
                    $userId,
                );
                $created++;
            }
        }

        return $created;
    }

    /**
     * Referrals created within the last hour. Alerts are sent to all users
     * belonging to the target agency.
     */
    private function checkNewReferral(): int
    {
        $referrals = DB::table('referrals')
            ->where('is_deleted', false)
            ->where('created_at', '>=', now()->subHour())
            ->get();

        $created = 0;

        foreach ($referrals as $referral) {
            if ($this->hasActiveAlert('new_referral', $referral->id)) {
                continue;
            }

            $agencyUsers = DB::table('users')
                ->where('agcy_id', $referral->agcy_id)
                ->where('is_deleted', false)
                ->get();

            foreach ($agencyUsers as $user) {
                $this->createAlert(
                    'new_referral',
                    'info',
                    'New referral assigned',
                    'A new referral has been assigned to your agency.',
                    'referral',
                    $referral->id,
                    $user->id,
                );
                $created++;
            }
        }

        return $created;
    }

    /**
     * Feedback submitted within the last hour. Alerts are sent to the case
     * manager who owns the related case.
     */
    private function checkFeedbackSubmitted(): int
    {
        $feedback = DB::table('feedback')
            ->where('created_at', '>=', now()->subHour())
            ->get();

        $created = 0;

        foreach ($feedback as $item) {
            if ($this->hasActiveAlert('feedback_submitted', $item->id)) {
                continue;
            }

            $userId = DB::table('cases')
                ->where('id', $item->case_id)
                ->value('user_id');

            if ($userId) {
                $this->createAlert(
                    'feedback_submitted',
                    'info',
                    'Feedback submitted',
                    'New feedback has been submitted for a case under your management.',
                    'feedback',
                    $item->id,
                    $userId,
                );
                $created++;
            }
        }

        return $created;
    }

    /**
     * Agencies with 7–10 active referrals (approaching but not yet at the
     * overloaded threshold of 11). Alerts are sent to all ADMIN users.
     */
    private function checkCapacityWarning(): int
    {
        $agencies = DB::table('referrals')
            ->select('agcy_id')
            ->selectRaw('COUNT(*) as referral_count')
            ->where('is_deleted', false)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'FOR COMPLIANCE'])
            ->groupBy('agcy_id')
            ->havingRaw('COUNT(*) > 7')
            ->havingRaw('COUNT(*) <= 10')
            ->get();

        $created = 0;
        $admins = null;

        foreach ($agencies as $item) {
            if ($this->hasActiveAlert('capacity_warning', $item->agcy_id)) {
                continue;
            }

            $agencyName = DB::table('agencies')
                ->where('id', $item->agcy_id)
                ->value('name') ?? 'Unknown Agency';

            if ($admins === null) {
                $admins = DB::table('users')
                    ->where('role', 'ADMIN')
                    ->where('is_deleted', false)
                    ->get();
            }

            foreach ($admins as $admin) {
                $this->createAlert(
                    'capacity_warning',
                    'info',
                    "Agency nearing capacity: {$agencyName}",
                    "This agency has {$item->referral_count} active referrals and is approaching capacity.",
                    'agency',
                    $item->agcy_id,
                    $admin->id,
                );
                $created++;
            }
        }

        return $created;
    }
}
