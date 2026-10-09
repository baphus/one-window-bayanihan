<?php

namespace App\Models;

use App\Models\Concerns\CascadeSoftDeletes;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use CascadeSoftDeletes, HasFactory, SoftDeleteFlag, UsesUuid;

    /**
     * Relationships to cascade on soft-delete and restore.
     */
    protected array $cascadeSoftDeletes = ['comments', 'attachments'];

    /**
     * Controlled rejection-reason vocabulary (mirrors the
     * referrals_rejection_reason_check constraint).
     */
    public const REJECTION_REASONS = [
        'INCOMPLETE_REQUIREMENTS',
        'OUTSIDE_MANDATE',
        'DUPLICATE_REFERRAL',
        'CLIENT_WITHDREW',
        'NO_SERVICE_CAPACITY',
        'OTHER',
    ];

    public const REJECTION_REASON_LABELS = [
        'INCOMPLETE_REQUIREMENTS' => 'Incomplete requirements',
        'OUTSIDE_MANDATE' => 'Outside mandate',
        'DUPLICATE_REFERRAL' => 'Duplicate referral',
        'CLIENT_WITHDREW' => 'Client withdrew',
        'NO_SERVICE_CAPACITY' => 'No service capacity',
        'OTHER' => 'Other',
    ];

    // Deliberately audited: rejection_reason is a controlled-vocabulary enum
    // like `decision` (safe for the audit page), not free text like
    // `decision_comment` (excluded from the safe audit response). The reason
    // stays out of per-row exports — it is served aggregate-only via
    // ReportsService::getRejectionReasonDistribution().
    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'case_id'];

    public function getAuditModuleName(): string
    {
        return 'referral';
    }

    public function getAuditEntityLabel(): ?string
    {
        return $this->required_services ?: null;
    }

    protected $fillable = [
        'required_services',
        'notes',
        'status',
        'decision',
        'decision_comment',
        'rejection_reason',
        'case_id',
        'agcy_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    protected $appends = [];

    /**
     * Get the latest update for this referral — either the most recent milestone
     * or a status change, whichever is newer.
     */
    public function getLatestUpdateAttribute(): ?array
    {
        $latestMilestone = $this->relationLoaded('milestones')
            ? $this->milestones->first()
            : $this->milestones()->latest()->first();

        // Determine the status update description and date
        $statusDescription = $this->getStatusDescription();
        $statusDate = $this->updated_at;

        // If there's a milestone, compare its date with the status update
        if ($latestMilestone) {
            $milestoneIsNewer = ! $statusDate || $latestMilestone->created_at->gte($statusDate);

            if ($milestoneIsNewer) {
                return [
                    'description' => $latestMilestone->title,
                    'date' => $latestMilestone->created_at?->format('F j, Y \a\t h:i A'),
                    'type' => 'milestone',
                ];
            }
        }

        // Show the status update
        if ($statusDescription && $this->updated_at) {
            return [
                'description' => $statusDescription,
                'date' => $this->updated_at->format('F j, Y \a\t h:i A'),
                'type' => 'status',
            ];
        }

        // Fallback: show when it was referred
        return [
            'description' => 'Referred to agency',
            'date' => $this->created_at?->format('F j, Y \a\t h:i A'),
            'type' => 'status',
        ];
    }

    public function rejectionReasonLabel(): ?string
    {
        if ($this->rejection_reason === null) {
            return null;
        }

        return self::REJECTION_REASON_LABELS[$this->rejection_reason] ?? $this->rejection_reason;
    }

    /**
     * Get a human-readable description for the current referral status.
     */
    private function getStatusDescription(): ?string
    {
        return match ($this->status) {
            'PENDING' => 'Sent to agency — awaiting response',
            'PROCESSING' => 'Accepted — now processing',
            'FOR_COMPLIANCE' => 'Set as For Compliance',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Rejected'
                .($this->rejection_reason ? ' ('.$this->rejectionReasonLabel().')' : '')
                .($this->decision_comment ? ': '.$this->decision_comment : ''),
            default => 'Status updated to '.$this->status,
        };
    }

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agcy_id');
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class, 'refr_id');
    }

    public function attachments()
    {
        return $this->hasMany(ReferralAttachment::class, 'referral_id');
    }

    public function comments()
    {
        return $this->hasMany(ReferralComment::class, 'refr_id');
    }

    public function messages()
    {
        return $this->hasMany(ReferralMessage::class, 'referral_id');
    }

    public function documents()
    {
        return $this->hasMany(CaseDocument::class, 'referral_id');
    }

    public function clientRequests()
    {
        return $this->hasMany(ReferralClientRequest::class, 'referral_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'referral_services', 'referral_id', 'service_id')
            ->withTimestamps();
    }

    public function serviceRequirements()
    {
        return $this->hasMany(ReferralServiceRequirement::class, 'referral_id');
    }
}
