<?php

namespace App\Services;

use App\Events\ReferralCompleted;
use App\Helpers\CacheHelper;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralComment;
use App\Models\User;
use App\Notifications\MilestoneAdded;
use App\Notifications\ReferralCreated;
use App\Notifications\ReferralStatusChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ReferralService
{
    public const REFERRAL_STATS_CACHE_VERSION_KEY = 'stats:referrals:version';

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly CaseEventRecorder $eventRecorder,
    ) {}

    public static function referralStatsCacheKey(?string $userAgencyId, ?string $userRole, ?string $userId): string
    {
        $version = (int) Cache::rememberForever(self::REFERRAL_STATS_CACHE_VERSION_KEY, static fn () => 1);

        return 'stats:referrals:v'.$version.':'.($userRole ?? 'all').':'.($userAgencyId ?? 'global').':'.($userId ?? 'global');
    }

    public static function invalidateReferralStats(): void
    {
        Cache::increment(self::REFERRAL_STATS_CACHE_VERSION_KEY);
    }

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

            $this->eventRecorder->referralSent($referral, $userId);

            // Pre-fill requirements from service requirements
            if (! empty($data['agcy_id'])) {
                $serviceReqs = $this->getServiceRequirements($data['agcy_id']);
                $requirements = [];
                foreach ($serviceReqs as $svc) {
                    foreach ($svc['requiredDocuments'] ?? [] as $doc) {
                        $requirements[] = $doc;
                    }
                }
                if (! empty($requirements)) {
                    $referral->update(['requirements' => $requirements]);
                    $referral->refresh();
                }
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

    public function getReferralStats(?string $userAgencyId = null, ?string $userRole = null, ?string $userId = null): array
    {
        $cacheKey = self::referralStatsCacheKey($userAgencyId, $userRole, $userId);

        return CacheHelper::safeRemember($cacheKey, 120, function () use ($userAgencyId, $userRole, $userId) {
            if ($userRole === 'AGENCY' && ! $userAgencyId) {
                return array_fill_keys(['total_referrals', 'pending', 'processing', 'for_compliance', 'completed', 'rejected'], 0);
            }

            if ($userRole === 'CASE_MANAGER' && ! $userId) {
                return array_fill_keys(['total_referrals', 'pending', 'processing', 'for_compliance', 'completed', 'rejected'], 0);
            }

            $where = 'WHERE is_deleted = false';
            $bindings = [];

            if ($userRole === 'AGENCY' && $userAgencyId) {
                $where .= ' AND agcy_id = ?';
                $bindings[] = $userAgencyId;
            }

            if ($userRole === 'CASE_MANAGER') {
                $where .= ' AND EXISTS (SELECT 1 FROM cases c WHERE c.id = referrals.case_id AND c.user_id = ? AND c.is_deleted = false)';
                $bindings[] = $userId;
            }

            $counts = DB::selectOne("
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'PENDING') AS pending,
                    COUNT(*) FILTER (WHERE status = 'PROCESSING') AS processing,
                    COUNT(*) FILTER (WHERE status = 'FOR_COMPLIANCE') AS for_compliance,
                    COUNT(*) FILTER (WHERE status = 'COMPLETED') AS completed,
                    COUNT(*) FILTER (WHERE status = 'REJECTED') AS rejected
                FROM referrals
                {$where}
            ", $bindings);

            return [
                'total_referrals' => (int) $counts->total,
                'pending' => (int) $counts->pending,
                'processing' => (int) $counts->processing,
                'for_compliance' => (int) $counts->for_compliance,
                'completed' => (int) $counts->completed,
                'rejected' => (int) $counts->rejected,
            ];
        });
    }

    public function getReferrals(array $filters = [], ?string $userAgencyId = null, ?string $userRole = null, ?string $userId = null)
    {
        $relations = [
            'caseFile.client.addresses',
            'caseFile.category',
            'caseFile.caseIssue',
            'agency',
            'milestones' => fn ($q) => $q->latest(),
        ];
        $relations[] = 'caseFile.categories';
        $query = Referral::with($relations)->orderBy('created_at', 'desc');

        if ($userRole === 'AGENCY') {
            if (! $userAgencyId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('agcy_id', $userAgencyId);
            }
        }

        if ($userRole === 'CASE_MANAGER') {
            if (! $userId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('caseFile', fn ($q) => $q
                    ->where('user_id', $userId)
                    ->where('is_deleted', false));
            }
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

        $categoryIds = $filters['category_ids'] ?? [];
        if (! is_array($categoryIds) || empty($categoryIds)) {
            $categoryIds = ! empty($filters['category_id']) ? [$filters['category_id']] : [];
        }
        $categoryIds = array_values(array_filter($categoryIds));

        if (! empty($categoryIds)) {
            $query->whereHas('caseFile', function ($q) use ($categoryIds) {
                $q->where(function ($categoryQuery) use ($categoryIds) {
                    $categoryQuery->whereIn('category_id', $categoryIds);
                    $categoryQuery->orWhereHas('categories', fn ($categories) => $categories->whereIn('case_categories.id', $categoryIds));
                });
            });
        }

        if (! empty($filters['case_issue_id'])) {
            $query->whereHas('caseFile', fn ($q) => $q->where('case_issue_id', $filters['case_issue_id']));
        }

        if (! empty($filters['age_min_days']) && is_numeric($filters['age_min_days'])) {
            $query->where('created_at', '<=', now()->subDays((int) $filters['age_min_days'])->endOfDay());
        }

        if (! empty($filters['age_max_days']) && is_numeric($filters['age_max_days'])) {
            $query->where('created_at', '>=', now()->subDays((int) $filters['age_max_days'])->startOfDay());
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
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

        return $query->paginate(15)->through(fn ($referral) => $referral->append('latest_update'));
    }

    public function getReferral(string $id): Referral
    {
        $relations = [
            'caseFile.client.addresses',
            'caseFile.client.employments',
            'caseFile.client.nextOfKin',
            'caseFile.user',
            'caseFile.category',
            'caseFile.caseIssue',
            'agency',
            'milestones.user',
            'attachments.user',
            'comments.user',
            'comments.replies.user',
        ];
        $relations[] = 'caseFile.categories';

        return Referral::with($relations)->findOrFail($id);
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
            // Audit logging is handled by AuditObserver::updated() — no manual log needed.

            if ($oldStatus !== $status) {
                $this->eventRecorder->referralStatusChanged($referral, $oldStatus, $status, $userId);
            }

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

    public function addMilestone(string $referralId, string $title, ?string $description, string $userId, ?array $requirements = null): Milestone
    {
        return DB::transaction(function () use ($referralId, $title, $description, $userId, $requirements) {
            $referral = Referral::findOrFail($referralId);

            if ($referral->status === 'COMPLETED') {
                throw new \InvalidArgumentException('Cannot add milestones to a completed referral.');
            }

            $milestone = Milestone::create([
                'title' => $title,
                'description' => $description,
                'requirements' => $requirements,
                'refr_id' => $referralId,
                'user_id' => $userId,
            ]);

            $this->eventRecorder->milestoneAdded($referral, $milestone, $userId);

            // Audit logging is handled by AuditObserver::created() — no manual log needed.

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

        if ($userRole === 'AGENCY') {
            $userAgencyId
                ? $query->where('agcy_id', $userAgencyId)
                : $query->whereRaw('1 = 0');
        }

        return $query->orderBy('created_at')->paginate(15);
    }

    /**
     * Get overdue referrals for the unified dashboard — used by all roles.
     *
     * Uses inactivity-based overdue (time since last milestone, status change, or creation).
     * Returns enriched data with severity bands, compliance progress, and aggregate stats.
     */
    public function getOverdueReferralsDashboard(
        string $userRole,
        ?string $userId,
        ?string $userAgencyId,
        int $overdueDays = 7,
        array $filters = [],
    ): array {
        $query = Referral::with([
            'caseFile.client:id,first_name,last_name',
            'caseFile.user:id,name',
            'agency:id,name',
            'milestones' => fn ($q) => $q->latest()->limit(1),
        ])
            ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
            ->whereRaw('EXTRACT(EPOCH FROM (NOW() - COALESCE(
                (SELECT MAX(created_at) FROM milestones WHERE refr_id = referrals.id),
                GREATEST(referrals.updated_at, referrals.created_at)
            ))) / 86400 > ?', [$overdueDays]);

        // Role-scoping
        match ($userRole) {
            'ADMIN' => null,
            'CASE_MANAGER' => $query->whereIn(
                'case_id', CaseFile::where('user_id', $userId)->select('id'),
            ),
            'AGENCY' => $userAgencyId
                ? $query->where('agcy_id', $userAgencyId)
                : $query->whereRaw('1 = 0'),
        };

        // Status filter
        $statusFilter = $filters['status_filter'] ?? 'all';
        if ($statusFilter !== 'all') {
            $query->where('status', strtoupper($statusFilter));
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'most_stale';
        $query->orderBy(
            match ($sortBy) {
                'status' => 'status',
                'client_name' => DB::raw('(SELECT CONCAT(first_name, \' \', last_name) FROM clients WHERE clients.id = (SELECT client_id FROM cases WHERE cases.id = referrals.case_id))'),
                default => DB::raw('EXTRACT(EPOCH FROM (NOW() - COALESCE(
                    (SELECT MAX(created_at) FROM milestones WHERE refr_id = referrals.id),
                    GREATEST(referrals.updated_at, referrals.created_at)
                )))'),
            },
            match ($sortBy) {
                'status' => 'asc',
                default => 'desc',
            },
        );

        $perPage = (int) ($filters['per_page'] ?? 15);
        $referrals = $query->paginate($perPage);

        // Transform each referral to enrich with computed attributes
        $now = now();
        $transformed = $referrals->getCollection()->map(function (Referral $referral) use ($now) {
            $latestMilestone = $referral->milestones->first();
            $lastActivityDate = $latestMilestone
                ? $latestMilestone->created_at
                : ($referral->updated_at ?? $referral->created_at);
            $daysSinceLastActivity = (int) $lastActivityDate->diffInDays($now);

            // Last activity description
            if ($latestMilestone) {
                $lastActivityDesc = 'Milestone: '.$latestMilestone->title.' — '.$daysSinceLastActivity.'d ago';
            } elseif ($referral->updated_at && $referral->updated_at->ne($referral->created_at)) {
                $lastActivityDesc = 'Status update — '.$daysSinceLastActivity.'d ago';
            } else {
                $lastActivityDesc = 'No activity yet';
            }

            // Severity
            $severity = match (true) {
                $daysSinceLastActivity >= 30 => 'severe',
                $daysSinceLastActivity >= 15 => 'moderate',
                default => 'mild',
            };

            return [
                'id' => $referral->id,
                'case_number' => $referral->caseFile?->case_number,
                'client_name' => $referral->caseFile?->client
                    ? trim(($referral->caseFile->client->first_name ?? '').' '.($referral->caseFile->client->last_name ?? ''))
                    : 'N/A',
                'required_services' => $referral->required_services,
                'status' => $referral->status,
                'agency_name' => $referral->agency?->name ?? 'N/A',
                'days_since_last_activity' => $daysSinceLastActivity,
                'severity' => $severity,
                'last_activity_description' => $lastActivityDesc,
                'last_activity_date' => $lastActivityDate->toIso8601String(),
                'case_manager_name' => $referral->caseFile?->user?->name ?? 'System',
                'created_at' => $referral->created_at->toIso8601String(),
            ];
        });

        $referrals->setCollection($transformed);

        // Aggregate stats
        $allItems = $transformed;
        $total = $allItems->count();
        $stats = [
            'total' => $total,
            'mild_count' => $allItems->where('severity', 'mild')->count(),
            'moderate_count' => $allItems->where('severity', 'moderate')->count(),
            'severe_count' => $allItems->where('severity', 'severe')->count(),
            'pending_count' => $allItems->where('status', 'PENDING')->count(),
            'processing_count' => $allItems->where('status', 'PROCESSING')->count(),
            'for_compliance_count' => $allItems->where('status', 'FOR_COMPLIANCE')->count(),
        ];

        // Determine bottleneck
        $statusCounts = [
            'pending' => $stats['pending_count'],
            'processing' => $stats['processing_count'],
            'for_compliance' => $stats['for_compliance_count'],
        ];
        arsort($statusCounts);
        $stats['bottleneck'] = array_key_first($statusCounts);

        return [
            'stats' => $stats,
            'referrals' => $referrals,
        ];
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

    public function deleteAttachment(string $attachmentId, string $userId): void
    {
        DB::transaction(function () use ($attachmentId, $userId) {
            $attachment = ReferralAttachment::findOrFail($attachmentId);

            if ($attachment->user_id !== $userId) {
                throw new \InvalidArgumentException('Only the uploader can remove this attachment.');
            }

            $fileName = $attachment->file_name;
            $referralId = $attachment->referral_id;

            $attachment->deleted_by = $userId;
            $attachment->is_deleted = true;
            $attachment->deleted_at = now();
            $attachment->saveQuietly();

            AuditLog::create([
                'action' => 'DELETE',
                'module' => 'referral_attachment',
                'entity_id' => $attachmentId,
                'old_value' => ['file_name' => $fileName, 'referral_id' => $referralId],
                'new_value' => null,
                'user_id' => $userId,
                'timestamp' => now(),
                'ip_address' => request()?->ip() ?? 'cli',
                'user_agent' => request()?->userAgent() ?? 'cli',
                'request_id' => request()?->attributes->get('correlation_id') ?? (string) Str::uuid(),
                'description' => "Removed attachment: {$fileName}",
            ]);
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
