<?php

namespace App\Models;

use App\Casts\EncryptedDate;
use App\Casts\EncryptedString;
use App\Models\Concerns\CascadeSoftDeletes;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseFile extends Model
{
    use CascadeSoftDeletes, HasFactory, SoftDeleteFlag, UsesUuid;

    /**
     * Relationships to cascade on soft-delete and restore.
     */
    protected array $cascadeSoftDeletes = ['referrals', 'documents'];

    public const INTAKE_FIELDS = [
        'ofw_user_id', 'intake_date', 'first_name', 'middle_name', 'last_name', 'suffix',
        'date_of_birth', 'sex', 'email', 'contact_number', 'civil_status', 'civil_status_other',
        'deployment_mode', 'employment_status', 'address_region', 'address_province',
        'address_city_municipality', 'address_barangay', 'address_street', 'address_region_code',
        'address_province_code', 'address_city_municipality_code', 'address_barangay_code',
        'employment_employer_name', 'employment_employer_address', 'employment_position', 'employment_country',
        'employment_last_position', 'employment_date_of_arrival',
        'nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email', 'staff_summary',
        'proposed_assessments', 'proposed_assessment_other', 'proposed_assessment_by', 'proposed_assessment_at',
        'confirmed_assessments', 'confirmed_assessment_other', 'confirmed_assessment_by', 'confirmed_assessment_at',
        'consent_text_version', 'consent_method', 'consent_evidence', 'consent_accepted_by',
        'return_reason', 'missing_document_reasons', 'requested_document_slots', 'document_request_reason',
        'document_requested_by', 'document_requested_at', 'reviewed_document_ids', 'assigned_case_manager_id', 'last_correction_reason',
    ];

    public const CIVIL_STATUSES = ['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED', 'ANNULLED', 'OTHER'];

    public const DEPLOYMENT_MODES = ['AGENCY_HIRED', 'DIRECT_HIRED', 'TOURIST_VISIT', 'BUSINESS', 'INVITATION'];

    public const EMPLOYMENT_STATUSES = ['ON_SITE', 'FOR_DEPLOYMENT', 'RETURNED'];

    public const ASSESSMENTS = [
        'DISTRESSED_OFW', 'MEDICALLY_REPATRIATED', 'BURIAL_ASSISTANCE_CONCERN', 'POSSIBLE_LOCAL_EMPLOYMENT',
        'PSYCHOSOCIAL_MEDICAL_INTERVENTION', 'SKILLS_ENHANCEMENT_TRAINING', 'LEGAL_ASSISTANCE', 'OTHER',
    ];

    // Intake is disclosed only by authorized, explicit payloads, never generic model serialization/auditing.
    protected $hidden = self::INTAKE_FIELDS;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'deletion_reason', ...self::INTAKE_FIELDS];

    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';

    public const CLIENT_TYPE_OFW = 'OFW';

    public const CLIENT_TYPE_NEXT_OF_KIN = 'NEXT_OF_KIN';

    public function getAuditModuleName(): string
    {
        return 'case';
    }

    public function getAuditEntityLabel(): ?string
    {
        return $this->case_number;
    }

    /**
     * Cases a client may access through self-service surfaces (public tracking
     * and the OFW portal). Anything not a personnel draft is visible —
     * PENDING_REVIEW included so filers can follow their submission.
     *
     * DRAFT is kept in this exclusion even though no code path writes it any
     * more: it is a defence-in-depth boundary, so a stray DRAFT row (restored
     * backup, manual DB edit, future feature) can never reach a client surface.
     */
    public function scopeClientVisible(Builder $query): Builder
    {
        return $query->where('cases.status', '!=', 'DRAFT');
    }

    /**
     * Live (non-deleted) cases. Task 2.2: query-builder replacement for the
     * raw client-directory stats SQL.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('cases.is_deleted', false);
    }

    /**
     * Cases that count toward directory tiles (excludes drafts + archived).
     *
     * DRAFT is kept as a defence-in-depth exclusion for the same reason as
     * clientVisible(): no path writes it, but a stray row must never inflate a
     * directory tile.
     */
    public function scopeWithVisibleStatus(Builder $query): Builder
    {
        return $query->whereNotIn('cases.status', ['DRAFT', 'ARCHIVED']);
    }

    /**
     * Open cases only (clients_with_open_cases tile).
     */
    public function scopeWithOpenStatus(Builder $query): Builder
    {
        return $query->where('cases.status', 'OPEN');
    }

    /**
     * Restrict to cases referred to the given agency. Null = no restriction.
     * All values are bound parameters (no string interpolation).
     */
    public function scopeForAgency(Builder $query, ?string $agencyId): Builder
    {
        if ($agencyId === null || $agencyId === '') {
            return $query;
        }

        return $query->whereHas('referrals', function (Builder $referrals) use ($agencyId) {
            $referrals->where('agcy_id', $agencyId)->where('is_deleted', false);
        });
    }

    /**
     * Vulnerability tiles: LIKE match on either indicator column.
     * Patterns are bound parameters via Eloquent.
     */
    public function scopeWithVulnerabilityMarker(Builder $query, string $marker): Builder
    {
        return $query->where(function (Builder $q) use ($marker) {
            $q->where('cases.vulnerability_indicator', 'LIKE', '%'.$marker.'%')
                ->orWhere('cases.nok_vulnerability_indicator', 'LIKE', '%'.$marker.'%');
        });
    }

    protected $table = 'cases';

    protected $fillable = [
        ...self::INTAKE_FIELDS,
        'case_number',
        'vulnerability_indicator',
        'nok_vulnerability_indicator',
        'tracker_number',
        'summary',
        'status',
        'closed_at',
        'consent_given_at',
        'user_id',
        'client_type',
        'deletion_reason',
        'reviewed_by',
        'reviewed_at',
        'document_requirements',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'closed_at' => 'datetime',
        'consent_given_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'intake_date' => 'date:Y-m-d',
        'date_of_birth' => EncryptedDate::class,
        'address_street' => EncryptedString::class,
        'employment_employer_name' => EncryptedString::class,
        'employment_employer_address' => EncryptedString::class,
        'missing_document_reasons' => 'encrypted:array',
        'document_request_reason' => EncryptedString::class,
        'last_correction_reason' => EncryptedString::class,
        'requested_document_slots' => 'array',
        'document_requirements' => 'array',
        'reviewed_document_ids' => 'array',
        'document_requested_at' => 'datetime',
        'employment_position' => EncryptedString::class,
        'employment_country' => EncryptedString::class,
        'employment_last_position' => EncryptedString::class,
        'employment_date_of_arrival' => 'date:Y-m-d',
        'nok_contact_number' => EncryptedString::class,
        'nok_email' => EncryptedString::class,
        'civil_status_other' => EncryptedString::class,
        'staff_summary' => EncryptedString::class,
        'proposed_assessments' => 'array',
        'confirmed_assessments' => 'array',
        'proposed_assessment_other' => EncryptedString::class,
        'confirmed_assessment_other' => EncryptedString::class,
        'proposed_assessment_at' => 'datetime',
        'confirmed_assessment_at' => 'datetime',
        'consent_evidence' => EncryptedString::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $case): void {
            // DOB is encrypted, so PostgreSQL cannot enforce this cross-field constraint.
            $case->age_at_intake;
        });
    }

    public function getAgeAtIntakeAttribute(): ?int
    {
        if ($this->date_of_birth === null || $this->intake_date === null) {
            return null;
        }

        if ($this->date_of_birth->toDateString() > $this->intake_date->toDateString()) {
            throw new \InvalidArgumentException('Date of birth must not follow intake date.');
        }

        return (int) $this->date_of_birth->diffInYears($this->intake_date);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'ofw_user_id');
    }

    public function ofwUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ofw_user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'case_id');
    }

    public function documents()
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function caseEvents()
    {
        return $this->hasMany(CaseEvent::class, 'case_id');
    }
}
