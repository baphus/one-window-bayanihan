<?php

namespace App\Services;

use App\Events\ReferralCompleted;
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
        return DB::transaction(function () use ($data) {
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

            // Audit logging is handled by AuditObserver::created() — no manual log needed.

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
            'caseFile.client.addresses',
            'caseFile.category',
            'caseFile.caseIssue',
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
                            ->orWhere('middle_initial', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate(15);
    }

    public function getReferral(string $id): Referral
    {
        return Referral::with([
            'caseFile.client.addresses',
            'caseFile.user',
            'caseFile.category',
            'caseFile.caseIssue',
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
        return DB::transaction(function () use ($id, $status, $decision, $decisionComment) {
            $referral = Referral::findOrFail($id);
            $oldStatus = $referral->status;

            $referral->update([
                'status' => $status,
                'decision' => $decision ?? $referral->decision,
                'decision_comment' => $decisionComment ?? $referral->decision_comment,
            ]);
            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

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

            // Dispatch ReferralCompleted event for feedback request
            if ($status === 'COMPLETED' && $oldStatus !== 'COMPLETED') {
                event(new ReferralCompleted($referral->fresh()));
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

            // Audit logging is handled by AuditObserver::created() — no manual log needed.

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

        // Audit logging is handled by AuditObserver::created() — no manual log needed.

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

        // Audit logging is handled by AuditObserver::created() — no manual log needed.

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

        // Audit logging is handled by AuditObserver::created() — no manual log needed.

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

            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

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

    /**
     * Build a unified referral timeline that includes referral sent, status changes,
     * and milestones — all sorted by timestamp.
     */
    public function getReferralTimeline(Referral $referral): array
    {
        $events = collect();

        // 1. Referral sent event
        $events->push([
            'id' => 'sent-'.$referral->id,
            'type' => 'referral_sent',
            'title' => 'Referral sent to '.($referral->agency?->name ?? 'agency'),
            'description' => $referral->required_services ?? '',
            'timestamp' => $referral->created_at->toISOString(),
            'actor' => $referral->caseFile?->user?->name ?? 'System',
        ]);

        // 2. Status changes from AuditLog (observer-created, module='referral')
        $statusLogs = AuditLog::with('user')
            ->where('module', 'referral')
            ->where('entity_id', $referral->id)
            ->where('action', 'UPDATE')
            ->orderBy('timestamp')
            ->get()
            ->filter(fn (AuditLog $log) => ($log->old_value['status'] ?? null) !== ($log->new_value['status'] ?? null));

        foreach ($statusLogs as $log) {
            $oldStatus = $log->old_value['status'] ?? 'UNKNOWN';
            $newStatus = $log->new_value['status'] ?? 'UNKNOWN';

            $events->push([
                'id' => 'status-'.$log->id,
                'type' => 'referral_status',
                'title' => 'Status changed from '.str_replace('_', ' ', $oldStatus).' to '.str_replace('_', ' ', $newStatus),
                'description' => $log->description ?? '',
                'timestamp' => $log->timestamp->toISOString(),
                'actor' => $log->user?->name ?? 'System',
            ]);
        }

        // 3. Milestones
        foreach ($referral->milestones->sortBy('created_at') as $ms) {
            $events->push([
                'id' => 'milestone-'.$ms->id,
                'type' => 'milestone',
                'title' => $ms->title,
                'description' => $ms->description ?? '',
                'timestamp' => $ms->created_at->toISOString(),
                'actor' => $ms->user?->name ?? 'System',
            ]);
        }

        return $events->sortBy('timestamp')->values()->toArray();
    }

    public function getAttachmentVersions(string $versionGroupId)
    {
        return ReferralAttachment::where('version_group_id', $versionGroupId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
