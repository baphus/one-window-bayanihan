<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackInvitation extends Model
{
    use HasFactory, UsesUuid;

    const RATING_LABELS = [
        ['value' => 1, 'label' => 'Strongly Disagree'],
        ['value' => 2, 'label' => 'Disagree'],
        ['value' => 3, 'label' => 'Neutral'],
        ['value' => 4, 'label' => 'Agree'],
        ['value' => 5, 'label' => 'Strongly Agree'],
    ];

    protected $fillable = [
        'case_id',
        'agency_id',
        'referral_id',
        'client_email',
        'token_prefix',
        'token_hash',
        'service_id',
        'service_name',
        'snapshot_source',
        'form_snapshot',
        'rating_labels',
        'expires_at',
        'submitted_at',
        'used_feedback_id',
    ];

    protected $casts = [
        'form_snapshot' => 'array',
        'rating_labels' => 'array',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function feedback()
    {
        return $this->belongsTo(Feedback::class, 'used_feedback_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isSubmitted();
    }

    public function scopeUsable($query)
    {
        return $query->whereNull('submitted_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeSubmitted($query)
    {
        return $query->whereNotNull('submitted_at');
    }
}
