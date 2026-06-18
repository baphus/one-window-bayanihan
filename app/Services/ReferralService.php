<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralComment;
use App\Models\ReferralComplianceRequirement;
use App\Models\User;
use App\Notifications\MilestoneAdded;
use App\Notifications\ReferralCreated;
use App\Notifications\ReferralStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ReferralService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function createReferral(array $data, string $userId): Referral
    {
        return DB::transaction(function () use ($data, $userId) {
            $services = ! empty($data['services']) && is_array($data['services'])
                ? implode(', ', $data['services'])
                : ($data['required_services'] ?? '');

            $referral = Referral::create([
                'required_services' => $services,
                'notes' => $data['notes'] ?? null,
                'status' => 'PENDING',
                'case_id' => $data['case_id'],
                'agcy_id' => $data['agcy_id'],
            ]);

            if (! empty($data['compliance_requirements'])) {
                foreach ($data['compliance_requirements'] as $req) {
                    ReferralComplianceRequirement::create([
                        'referral_id' => $referral->id,
                        'service_name' => $req['service_name'],
                        'requirement_name' => $req['requirement_name'],
                        'status' => 'PENDING',
                    ]);
                }
                $referral->update(['status' => 'FOR_COMPLIANCE']);
                $referral->refresh();
            }

            AuditLog::create([
                'action' => 'CREATE',
                'module' => 'REFERRAL',
                'entity_id' => $referral->id,
                'new_value' => $referral->toArray(),
                'user_id' => $userId,
            ]);

            // Notify agency users about the new referral
            $agencyUsers = User::where('agcy_id', $referral->agcy_id)
                ->where('is_active', true)
                ->get();
            Notification::send($agencyUsers, new ReferralCreated($referral));

            // Also create OFW notification for the case client
            if ($referral->caseFile && $referral->caseFile->client && $referral->caseFile->client->email) {
                $this->notificationService->notifyOfw(
                    $referral->caseFile,
                    $referral->caseFile->client->email,
                    'referral_created',
                    'New Referral',
                    'A new referral has been created for your case.',
                    ['referral_id' => $referral->id, 'status' => $referral->status],
                    route('track.show', $referral->caseFile->tracker_number ?? $referral->case_id),
                );
            }

            return $referral->load(['agency', 'caseFile', 'milestones']);
        });
    }

    public function getReferrals(array $filters = [], ?string $userAgencyId = null, ?string $userRole = null)
    {
        $query = Referral::with([
            'caseFile.client',
            'agency',
            'milestones' => fn ($q) => $q->latest(),
        ])->orderBy('created_at', 'desc');

        if ($userRole === 'AGENCY' && $userAgencyId) {
            $query->where('agcy_id', $userAgencyId);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['case_id'])) {
            $query->where('case_id', $filters['case_id']);
        }

        if (! empty($filters['agcy_id'])) {
            $query->where('agcy_id', $filters['agcy_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('required_services', 'like', "%{$search}%")
                    ->orWhereHas('caseFile', function ($q) use ($search) {
                        $q->where('case_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('agency', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('caseFile.client', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate(15);
    }

    public function getReferral(string $id): Referral
    {
        return Referral::with([
            'caseFile.client',
            'caseFile.user',
            'caseFile.comments.user',
            'caseFile.comments.replies.user',
            'agency',
            'milestones.user',
            'attachments.user',
            'comments.user',
            'comments.replies.user',
            'complianceRequirements',
        ])->findOrFail($id);
    }

    public function getServiceRequirements(string $agencyId): array
    {
        $agency = Agency::with('services.requirements')->find($agencyId);

        if (! $agency) {
            return [];
        }

        return $agency->services->map(function ($service) {
            return [
                'title' => $service->name,
                'requiredDocuments' => $service->requirements->pluck('name')->toArray(),
            ];
        })->toArray();
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

            // Notify case manager about the status change
            if ($referral->caseFile) {
                $caseManager = User::find($referral->caseFile->user_id);
                if ($caseManager) {
                    Notification::send(
                        [$caseManager],
                        new ReferralStatusChanged($referral, $oldStatus, $status),
                    );
                }

                // Also create OFW notification
                if ($referral->caseFile->client && $referral->caseFile->client->email) {
                    $this->notificationService->notifyOfw(
                        $referral->caseFile,
                        $referral->caseFile->client->email,
                        'referral_status_changed',
                        'Referral Status Updated',
                        "Referral status changed from {$oldStatus} to {$status}.",
                        [
                            'referral_id' => $referral->id,
                            'old_status' => $oldStatus,
                            'new_status' => $status,
                        ],
                        route('track.show', $referral->caseFile->tracker_number ?? $referral->case_id),
                    );
                }
            }

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

            // Dispatch notifications for the milestone
            $referral = $milestone->referral ?? Referral::find($referralId);
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

                $this->notificationService->notifyAll(
                    $referral->caseFile,
                    $notifyUsers->unique('id')->all(),
                    $clientEmail,
                    new MilestoneAdded($milestone, $referral),
                    'milestone_added',
                    'New Milestone Added',
                    "New milestone '{$title}' added to referral.",
                    ['referral_id' => $referralId, 'milestone_id' => $milestone->id, 'milestone_title' => $title],
                    route('referrals.show', $referral->id),
                );
            }

            return $milestone->load('user');
        });
    }

    public function getOverdueReferrals(int $overdueDays = 7, ?string $userAgencyId = null, ?string $userRole = null)
    {
        $cutoff = now()->subDays($overdueDays);

        $query = Referral::with([
            'caseFile.client',
            'agency.users' => fn ($q) => $q->where('role', 'AGENCY')->where('is_active', true),
        ])
            ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
            ->where('created_at', '<', $cutoff);

        if ($userRole === 'AGENCY' && $userAgencyId) {
            $query->where('agcy_id', $userAgencyId);
        }

        return $query->orderBy('created_at')->paginate(15);
    }

    public function getAgenciesWithServices()
    {
        return Agency::with('services.requirements')->where('is_deleted', false)->get();
    }

    public function addComment(string $referralId, string $content, string $userId, string $visibility = 'INTERNAL'): ReferralComment
    {
        $comment = ReferralComment::create([
            'refr_id' => $referralId,
            'content' => $content,
            'visibility' => $visibility,
            'user_id' => $userId,
        ]);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'REFERRAL_COMMENT',
            'entity_id' => $comment->id,
            'new_value' => $comment->toArray(),
            'user_id' => $userId,
        ]);

        return $comment->load('user');
    }

    public function replyToComment(string $commentId, string $content, string $userId, string $visibility = 'INTERNAL'): ReferralComment
    {
        $parent = ReferralComment::findOrFail($commentId);

        $reply = ReferralComment::create([
            'refr_id' => $parent->refr_id,
            'parent_id' => $commentId,
            'content' => $content,
            'visibility' => $visibility,
            'user_id' => $userId,
        ]);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'REFERRAL_REPLY',
            'entity_id' => $reply->id,
            'new_value' => $reply->toArray(),
            'user_id' => $userId,
        ]);

        return $reply->load('user');
    }

    public function addAttachment(string $referralId, array $fileData, string $userId): ReferralAttachment
    {
        $attachment = ReferralAttachment::create([
            'referral_id' => $referralId,
            'file_name' => $fileData['name'],
            'file_path' => $fileData['path'],
            'file_type' => $fileData['type'] ?? null,
            'size' => $fileData['size'] ?? null,
            'user_id' => $userId,
            'version_group_id' => (string) Str::uuid(),
        ]);

        AuditLog::create([
            'action' => 'CREATE',
            'module' => 'REFERRAL_ATTACHMENT',
            'entity_id' => $attachment->id,
            'new_value' => $attachment->toArray(),
            'user_id' => $userId,
        ]);

        return $attachment->load('user');
    }

    public function fulfillCompliance(string $complianceId, array $fileData, string $userId): ReferralAttachment
    {
        return DB::transaction(function () use ($complianceId, $fileData, $userId) {
            $requirement = ReferralComplianceRequirement::findOrFail($complianceId);

            if ($requirement->status !== 'PENDING') {
                throw new \InvalidArgumentException('Compliance requirement is not pending.');
            }

            $attachment = $this->addAttachment($requirement->referral_id, $fileData, $userId);

            $requirement->update([
                'status' => 'COMPLIED',
                'fulfilled_by' => $userId,
                'completed_at' => now(),
            ]);

            AuditLog::create([
                'action' => 'CREATE',
                'module' => 'REFERRAL_COMPLIANCE',
                'entity_id' => $requirement->id,
                'new_value' => $requirement->fresh()->toArray(),
                'user_id' => $userId,
            ]);

            return $attachment;
        });
    }

    public function replaceAttachment(string $attachmentId, array $fileData, string $userId): ReferralAttachment
    {
        return DB::transaction(function () use ($attachmentId, $fileData, $userId) {
            $oldAttachment = ReferralAttachment::findOrFail($attachmentId);
            $versionGroupId = $oldAttachment->version_group_id ?? (string) Str::uuid();

            $oldAttachment->update([
                'is_archived' => true,
                'version_group_id' => $versionGroupId,
            ]);

            $newAttachment = ReferralAttachment::create([
                'referral_id' => $oldAttachment->referral_id,
                'file_name' => $fileData['name'],
                'file_path' => $fileData['path'],
                'file_type' => $fileData['type'] ?? null,
                'size' => $fileData['size'] ?? null,
                'user_id' => $userId,
                'replaces_id' => $oldAttachment->id,
                'version_group_id' => $versionGroupId,
            ]);

            return $newAttachment->load('user');
        });
    }

    public function getAttachmentVersions(string $versionGroupId)
    {
        return ReferralAttachment::where('version_group_id', $versionGroupId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
