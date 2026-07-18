<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'case_id'];

    public function getAuditModuleName(): string
    {
        return 'referral';
    }

    protected $fillable = [
        'required_services',
        'requirements',
        'notes',
        'status',
        'decision',
        'decision_comment',
        'case_id',
        'agcy_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'requirements' => 'array',
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
                    'date' => $latestMilestone->created_at?->format('M d, Y h:i A'),
                    'type' => 'milestone',
                ];
            }
        }

        // Show the status update
        if ($statusDescription && $this->updated_at) {
            return [
                'description' => $statusDescription,
                'date' => $this->updated_at->format('M d, Y h:i A'),
                'type' => 'status',
            ];
        }

        // Fallback: show when it was referred
        return [
            'description' => 'Referred to agency',
            'date' => $this->created_at?->format('M d, Y h:i A'),
            'type' => 'status',
        ];
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
            'REJECTED' => 'Rejected'.($this->decision_comment ? ': '.$this->decision_comment : ''),
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
}
