<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyInvitation extends Model
{
    use HasFactory, UsesUuid;

    protected $hidden = [
        'token',
        'token_hash',
    ];

    protected $fillable = [
        'survey_form_id',
        'case_id',
        'agency_id',
        'referral_id',
        'client_name',
        'client_email',
        'service_name',
        'token_hash',
        'expires_at',
        'submitted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function surveyForm(): BelongsTo
    {
        return $this->belongsTo(SurveyForm::class);
    }

    public function caseFile(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * Check if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the invitation has already been submitted.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Check if the invitation is still usable (not expired and not submitted).
     */
    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isSubmitted();
    }

    /**
     * Scope: only usable invitations.
     */
    public function scopeUsable($query)
    {
        return $query->whereNull('submitted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: submitted invitations.
     */
    public function scopeSubmitted($query)
    {
        return $query->whereNotNull('submitted_at');
    }
}
