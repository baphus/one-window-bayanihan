<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\Milestone;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function createReferral(array $data, string $userId): Referral
    {
        return DB::transaction(function () use ($data, $userId) {
            $services = !empty($data['services']) && is_array($data['services'])
                ? implode(', ', $data['services'])
                : ($data['required_services'] ?? '');

            $referral = Referral::create([
                'required_services' => $services,
                'notes' => $data['notes'] ?? null,
                'status' => 'PENDING',
                'case_id' => $data['case_id'],
                'agcy_id' => $data['agcy_id'],
            ]);

            AuditLog::create([
                'action' => 'CREATE',
                'module' => 'REFERRAL',
                'entity_id' => $referral->id,
                'new_value' => $referral->toArray(),
                'user_id' => $userId,
            ]);

            return $referral->load(['agency', 'caseFile', 'milestones']);
        });
    }

    public function getReferrals(array $filters = [], ?string $userAgencyId = null, ?string $userRole = null)
    {
        $query = Referral::with([
            'caseFile.client',
            'agency',
            'milestones' => fn($q) => $q->latest(),
        ])->orderBy('created_at', 'desc');

        if ($userRole === 'AGENCY' && $userAgencyId) {
            $query->where('agcy_id', $userAgencyId);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['case_id'])) {
            $query->where('case_id', $filters['case_id']);
        }

        if (!empty($filters['agcy_id'])) {
            $query->where('agcy_id', $filters['agcy_id']);
        }

        return $query->paginate(15);
    }

    public function getReferral(string $id): Referral
    {
        return Referral::with([
            'caseFile.client',
            'caseFile.user',
            'agency',
            'milestones.user',
            'attachments.uploader',
        ])->findOrFail($id);
    }

    public function updateStatus(string $id, string $status, ?string $decision, ?string $decisionComment, string $userId): Referral
    {
        return DB::transaction(function () use ($id, $status, $decision, $decisionComment, $userId) {
            $referral = Referral::findOrFail($id);
            $oldStatus = $referral->status;

            $referral->update([
                'status' => $status,
                'decision' => $decision ?? $referral->decision,
                'decision_comment' => $decisionComment ?? $referral->decision_comment,
            ]);

            AuditLog::create([
                'action' => 'UPDATE',
                'module' => 'REFERRAL',
                'entity_id' => $referral->id,
                'description' => "Referral status changed from {$oldStatus} to {$status}",
                'old_value' => ['status' => $oldStatus],
                'new_value' => ['status' => $status, 'decision' => $decision, 'decision_comment' => $decisionComment],
                'user_id' => $userId,
            ]);

            return $referral->fresh(['agency', 'caseFile', 'milestones']);
        });
    }

    public function addMilestone(string $referralId, string $title, ?string $description, string $userId): Milestone
    {
        return DB::transaction(function () use ($referralId, $title, $description, $userId) {
            $milestone = Milestone::create([
                'title' => $title,
                'description' => $description,
                'refr_id' => $referralId,
                'user_id' => $userId,
            ]);

            AuditLog::create([
                'action' => 'CREATE',
                'module' => 'MILESTONE',
                'entity_id' => $milestone->id,
                'new_value' => $milestone->toArray(),
                'user_id' => $userId,
            ]);

            return $milestone->load('user');
        });
    }

    public function getAgenciesWithServices()
    {
        return \App\Models\Agency::with('services.requirements')->where('is_deleted', false)->get();
    }
}
