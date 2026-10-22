<?php

namespace App\Models;

use App\Models\Concerns\CascadeSoftDeletes;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseFile extends Model
{
    use CascadeSoftDeletes, HasFactory, SoftDeleteFlag, UsesUuid;

    /**
     * Relationships to cascade on soft-delete and restore.
     */
    protected array $cascadeSoftDeletes = ['referrals', 'documents'];

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'deletion_reason'];

    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';

    /** @deprecated Intake-era constants. Cases no longer carry source/client_type; kept so Phase 1b callers fail loudly at review, not silently. */
    public const CLIENT_TYPE_OFW = 'OFW';

    /** @deprecated See CLIENT_TYPE_OFW. */
    public const CLIENT_TYPE_NEXT_OF_KIN = 'NEXT_OF_KIN';

    /** @deprecated See CLIENT_TYPE_OFW. */
    public const CLIENT_TYPES = [
        self::CLIENT_TYPE_OFW,
        self::CLIENT_TYPE_NEXT_OF_KIN,
    ];

    /** @deprecated See CLIENT_TYPE_OFW. */
    public const SOURCE_INTERNAL = 'internal';

    /** @deprecated See CLIENT_TYPE_OFW. */
    public const SOURCE_SELF_FILED = 'self_filed';

    /** @deprecated See CLIENT_TYPE_OFW. */
    public const SOURCES = [
        self::SOURCE_INTERNAL,
        self::SOURCE_SELF_FILED,
    ];

    public function getAuditModuleName(): string
    {
        return 'case';
    }

    public function getAuditEntityLabel(): ?string
    {
        return $this->case_number;
    }

    /**
     * Derived from the case's client (`clients.type` — 'OFW' or 'NEXT_OF_KIN').
     *
     * Uses the eager-loaded `client` relation when present and falls back to a
     * lazy load, so callers that build payloads must eager-load `client` to
     * avoid N+1 (see CaseService::getCases/getCase, ClientController, and
     * ReferralService::getReferral, which all do).
     */
    public function getClientTypeAttribute(): ?string
    {
        if (! $this->client_id) {
            return null;
        }

        $client = $this->relationLoaded('client') ? $this->getRelation('client') : $this->client;

        return $client?->type;
    }

    /**
     * Cases a client may access through self-service surfaces (public tracking
     * and the OFW portal). Anything not a personnel draft is visible —
     * PENDING_REVIEW included so filers can follow their submission.
     */
    public function scopeClientVisible(Builder $query): Builder
    {
        return $query->where('status', '!=', 'DRAFT');
    }

    /**
     * Live (non-deleted) cases. Task 2.2: query-builder replacement for the
     * raw client-stats SQL in ClientController::getClientStats.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('cases.is_deleted', false);
    }

    /**
     * Cases that count toward directory tiles (excludes drafts + archived).
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
     * Filter by client type (OFW / NEXT_OF_KIN tiles). Type lives on
     * clients.type — cases no longer carry client_type.
     */
    public function scopeOfClientType(Builder $query, string $clientType): Builder
    {
        return $query->whereHas('client', fn (Builder $c) => $c->where('type', $clientType));
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
        'case_number',
        'vulnerability_indicator',
        'nok_vulnerability_indicator',
        'tracker_number',
        'summary',
        'status',
        'closed_at',
        'consent_given_at',
        'user_id',
        'client_id',
        'category_id',
        'case_issue_id',
        'deletion_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'closed_at' => 'datetime',
        'consent_given_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * The `client_type` column was dropped (2026_10_15_072238); the source of
     * truth is `clients.type`. It is re-exposed as an appended accessor so the
     * case index, case detail, referral screens, client directory and the case
     * report PDF keep reading `case.client_type` in Inertia payloads and Blade.
     *
     * @see CaseFile::getClientTypeAttribute()
     */
    protected $appends = ['client_type'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function category()
    {
        return $this->belongsTo(CaseCategory::class, 'category_id');
    }

    public function categories()
    {
        return $this->belongsToMany(CaseCategory::class, 'case_category', 'case_id', 'case_category_id')
            ->withTimestamps();
    }

    public function caseIssue()
    {
        return $this->belongsTo(CaseIssue::class, 'case_issue_id');
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
