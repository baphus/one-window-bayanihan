<?php

namespace App\Services\Referral;

use App\Models\Milestone;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\MilestoneAdded;
use App\Notifications\PeerReferralCreated;
use App\Notifications\ReferralCreated;
use App\Notifications\ReferralStatusChanged;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ReferralNotifier
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function notifyCreated(Referral $referral, string $userId): void
    {
        $actorName = User::whereKey($userId)->value('name');

        // Notify agency users about the new referral
        $agencyUsers = User::where('agcy_id', $referral->agcy_id)
            ->where('is_active', true)
            ->get();
        Notification::send($agencyUsers, new ReferralCreated($referral, $actorName));

        // Notify peer agencies already involved in this case
        $peerReferrals = Referral::where('case_id', $referral->case_id)
            ->where('id', '!=', $referral->id)
            ->where('is_deleted', false)
            ->where('agcy_id', '!=', $referral->agcy_id)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE'])
            ->get();

        if ($peerReferrals->isNotEmpty()) {
            $peerAgencyIds = $peerReferrals->pluck('agcy_id')->unique()->values();
            $peerUsers = User::whereIn('agcy_id', $peerAgencyIds)
                ->where('is_active', true)
                ->get();

            if ($peerUsers->isNotEmpty()) {
                Notification::send($peerUsers, new PeerReferralCreated($referral, $peerReferrals->first(), $actorName));
            }
        }

        // Also create OFW notification for the case client (plain language, no status codes).
        // Deferred until after commit: notifyOfw queues the client email,
        // which must never send when the referral transaction rolls back.
        if ($referral->caseFile && $referral->caseFile->client && $referral->caseFile->client->email) {
            $referral->loadMissing(['agency', 'services']);
            $agencyName = $referral->agency?->name ?? 'a partner agency';
            $services = $referral->relationLoaded('services') && $referral->services->isNotEmpty()
                ? $referral->services->pluck('name')->implode(', ')
                : 'the help you requested';

            $ofwCase = $referral->caseFile;
            $ofwEmail = $referral->caseFile->client->email;
            $trackUrl = route('track.show', $referral->caseFile->tracker_number ?? $referral->case_id);

            DB::afterCommit(fn () => $this->notificationService->notifyOfw(
                $ofwCase,
                $ofwEmail,
                'referral_created',
                "Your case was sent to {$agencyName}",
                "Good news — your case {$ofwCase->case_number} was sent to {$agencyName} for: {$services}. They will post updates here as they work on it.",
                ['referral_id' => $referral->id, 'status' => $referral->status],
                $trackUrl,
            ));
        }
    }

    public function notifyStatusChanged(Referral $referral, string $oldStatus, string $status, string $userId, ?string $rejectionReason = null): void
    {
        // Notify the case manager and the owning agency about the status change
        if ($referral->caseFile) {
            $actorName = User::whereKey($userId)->value('name');
            $reason = $status === 'REJECTED'
                ? ($rejectionReason ?? $referral->rejection_reason)
                : null;
            $statusNotification = new ReferralStatusChanged($referral, $oldStatus, $status, $actorName, $reason);

            $caseManager = User::find($referral->caseFile->user_id);
            if ($caseManager) {
                Notification::send([$caseManager], $statusNotification);
            }

            $agencyUsers = User::where('agcy_id', $referral->agcy_id)
                ->where('is_active', true)
                ->when($caseManager, fn ($query) => $query->where('id', '!=', $caseManager->id))
                ->get();
            if ($agencyUsers->isNotEmpty()) {
                Notification::send($agencyUsers, $statusNotification);
            }

            // Also create OFW notification (plain language, no status codes).
            // Deferred until after commit: notifyOfw queues the client email,
            // which must never send when the status-change transaction rolls back.
            if ($referral->caseFile->client && $referral->caseFile->client->email) {
                $referral->loadMissing('agency');
                $ofwCase = $referral->caseFile;
                $ofwEmail = $referral->caseFile->client->email;
                $ofwTitle = $this->ofwReferralStatusTitle($referral, $status);
                $ofwMessage = $this->ofwReferralStatusMessage($referral, $status);
                $trackUrl = route('track.show', $referral->caseFile->tracker_number ?? $referral->case_id);

                DB::afterCommit(fn () => $this->notificationService->notifyOfw(
                    $ofwCase,
                    $ofwEmail,
                    'referral_status_changed',
                    $ofwTitle,
                    $ofwMessage,
                    [
                        'referral_id' => $referral->id,
                        'old_status' => $oldStatus,
                        'new_status' => $status,
                    ],
                    $trackUrl,
                ));
            }
        }
    }

    public function notifyMilestone(Referral $referral, Milestone $milestone, string $title, string $userId): void
    {
        // Dispatch notifications for the milestone (already loaded above)
        if ($referral && $referral->caseFile) {
            $caseManager = User::find($referral->caseFile->user_id);
            $agencyUsers = User::where('agcy_id', $referral->agcy_id)
                ->where('is_active', true)
                ->get();

            $notifyUsers = collect();
            if ($caseManager) {
                $notifyUsers->push($caseManager);
            }
            foreach ($agencyUsers as $au) {
                $notifyUsers->push($au);
            }

            $clientEmail = $referral->caseFile->client?->email ?? '';
            $actorName = User::whereKey($userId)->value('name');
            $agencyName = $referral->agency?->name ?? 'the agency';
            $caseNumber = $referral->caseFile?->case_number ?? '';

            $this->notificationService->notifyAll(
                $referral->caseFile,
                $notifyUsers->unique('id')->all(),
                $clientEmail,
                new MilestoneAdded($milestone, $referral, $actorName),
                'milestone_added',
                "New update on your case from {$agencyName}",
                "{$agencyName} posted an update on your case {$caseNumber}: '{$title}'. Open your case to read the details.",
                ['referral_id' => $referral->id, 'milestone_id' => $milestone->id, 'milestone_title' => $title],
                route('referrals.show', $referral->id),
            );
        }
    }

    /**
     * Client-facing headline for a referral status change.
     * Plain language only — internal status codes never reach the client.
     */
    private function ofwReferralStatusTitle(Referral $referral, string $status): string
    {
        $agencyName = $referral->agency?->name ?? 'the agency';

        return match ($status) {
            'PROCESSING' => "{$agencyName} is now working on your case",
            'FOR_COMPLIANCE' => "Action needed: {$agencyName} needs something from you",
            'COMPLETED' => "{$agencyName} finished their part of your case",
            'REJECTED' => "Update on your referral with {$agencyName}",
            default => "{$agencyName} received your referral",
        };
    }

    /**
     * Client-facing body for a referral status change.
     * Plain language only — internal status codes never reach the client.
     */
    private function ofwReferralStatusMessage(Referral $referral, string $status): string
    {
        $agencyName = $referral->agency?->name ?? 'the agency';
        $caseNumber = $referral->caseFile?->case_number ?? '';

        return match ($status) {
            'PROCESSING' => "Good news — {$agencyName} has started working on your case {$caseNumber}. They will post updates here as they progress.",
            'FOR_COMPLIANCE' => "{$agencyName} needs additional information for your case {$caseNumber}. Please watch for their message and respond as soon as you can.",
            'COMPLETED' => "{$agencyName} has finished their part of your case {$caseNumber}. Thank you for your patience.",
            'REJECTED' => "{$agencyName} could not take on this part of your case {$caseNumber}. Your case manager will tell you what happens next.",
            default => "{$agencyName} received the referral for your case {$caseNumber} and will start on it shortly.",
        };
    }
}
