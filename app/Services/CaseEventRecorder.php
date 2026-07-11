<?php

namespace App\Services;

use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralComplianceRequirement;
use App\Models\User;

/**
 * Single write point for client-facing case events.
 *
 * All event phrasing lives here so copy stays consistent. Callers must invoke
 * these methods inside the same transaction as the domain mutation they record.
 */
class CaseEventRecorder
{
    public function caseOpened(CaseFile $case, ?string $userId = null): CaseEvent
    {
        return $this->record($case->id, null, CaseEvent::TYPE_CASE_OPENED, [
            'title' => 'Your case has been opened',
            'description' => 'Your case is now with the Bayanihan team for review and referral to partner agencies.',
        ], $userId);
    }

    public function caseClosed(CaseFile $case, ?string $userId = null): CaseEvent
    {
        return $this->record($case->id, null, CaseEvent::TYPE_CASE_CLOSED, [
            'title' => 'Your case has been resolved',
            'description' => 'All referrals have been processed and your case is now closed.',
        ], $userId);
    }

    public function caseReopened(CaseFile $case, ?string $userId = null): CaseEvent
    {
        return $this->record($case->id, null, CaseEvent::TYPE_CASE_REOPENED, [
            'title' => 'Your case has been reopened',
            'description' => 'Your case has been reopened for further action by the Bayanihan team.',
        ], $userId);
    }

    public function referralSent(Referral $referral, ?string $userId = null): CaseEvent
    {
        $agency = $this->agencyName($referral);

        return $this->record($referral->case_id, $referral->id, CaseEvent::TYPE_REFERRAL_SENT, [
            'title' => 'Referred to '.$agency,
            'description' => $referral->required_services
                ? 'Services requested: '.$referral->required_services
                : null,
        ], $userId);
    }

    public function referralStatusChanged(Referral $referral, string $from, string $to, ?string $userId = null): CaseEvent
    {
        $agency = $this->agencyName($referral);

        [$title, $description] = match ($to) {
            'PROCESSING' => [
                $agency.' is now processing your referral',
                'The agency is actively working on your request.',
            ],
            'FOR_COMPLIANCE' => [
                $agency.' needs additional requirements',
                'Please prepare the documents listed under this agency\'s requirements.',
            ],
            'COMPLETED' => [
                'Your referral with '.$agency.' has been completed',
                'The agency has finished its part of your case.',
            ],
            'REJECTED' => [
                $agency.' was unable to process your referral',
                'Your case manager will advise you on the next steps.',
            ],
            'PENDING' => [
                'Your referral with '.$agency.' is awaiting action',
                null,
            ],
            default => [
                $agency.' updated your referral',
                null,
            ],
        };

        return $this->record($referral->case_id, $referral->id, CaseEvent::TYPE_REFERRAL_STATUS_CHANGED, [
            'title' => $title,
            'description' => $description,
            'meta' => ['from' => $from, 'to' => $to],
        ], $userId);
    }

    public function milestoneAdded(Referral $referral, Milestone $milestone, ?string $userId = null): CaseEvent
    {
        return $this->record($referral->case_id, $referral->id, CaseEvent::TYPE_MILESTONE_ADDED, [
            'title' => $milestone->title,
            'description' => $milestone->description,
        ], $userId);
    }

    public function complianceFulfilled(Referral $referral, ReferralComplianceRequirement $requirement, ?string $userId = null): CaseEvent
    {
        $agency = $this->agencyName($referral);

        return $this->record($referral->case_id, $referral->id, CaseEvent::TYPE_COMPLIANCE_FULFILLED, [
            'title' => 'A requirement for '.$agency.' has been submitted',
            'description' => $requirement->requirement_name.' — '.$requirement->service_name,
        ], $userId);
    }

    private function record(string $caseId, ?string $referralId, string $type, array $payload, ?string $userId): CaseEvent
    {
        return CaseEvent::create([
            'case_id' => $caseId,
            'referral_id' => $referralId,
            'type' => $type,
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'actor_type' => $this->actorType($userId),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Events carry only an actor category — never a staff name or user ID.
     */
    private function actorType(?string $userId): string
    {
        if (! $userId) {
            return 'system';
        }

        return match (User::find($userId)?->role) {
            'AGENCY' => 'agency',
            'CASE_MANAGER', 'ADMIN' => 'case_manager',
            default => 'system',
        };
    }

    private function agencyName(Referral $referral): string
    {
        $referral->loadMissing('agency');

        return $referral->agency?->name ?? 'a partner agency';
    }
}
