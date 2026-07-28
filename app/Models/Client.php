<?php

namespace App\Models;

use App\Casts\EncryptedDate;
use App\Models\Concerns\HasAvatar;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasAvatar, HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
        'email', 'contact_number', 'date_of_birth', 'sex',
    ];

    public function getAuditModuleName(): string
    {
        return 'client';
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_initial',
        'suffix',
        'date_of_birth',
        'sex',
        'email',
        'contact_number',
        'avatar_url',
    ];

    protected $casts = [
        'date_of_birth' => EncryptedDate::class,
        'is_deleted' => 'boolean',
    ];

    public function caseFiles()
    {
        return $this->hasMany(CaseFile::class, 'client_id');
    }

    /**
     * Hide people who exist only because of a self-filed intake nobody has
     * reviewed yet.
     *
     * IntakeService writes the Client row when the public form is submitted, so
     * from that moment an unverified claim is indistinguishable from an
     * established client in any plain `Client::where('is_deleted', false)` query.
     * That put unreviewed filers in the case-creation client picker, where
     * selecting one attaches a real case to data no Case Manager has checked.
     *
     * A client is hidden only while EVERY live case they have is a pending
     * self-filed intake. Anyone with a case that has been through review — open,
     * closed, or a Case Manager's own internal draft — stays visible, so repeat
     * filers remain selectable. Clients with no cases at all are untouched.
     *
     * Rejected intakes are soft-deleted, and CaseFile's SoftDeletes scope keeps
     * them out of `caseFiles`, so a rejection also lifts the hiding.
     *
     * Deliberately a scope rather than a global scope: the intake queue and the
     * review screens must still see these records, and a global scope would have
     * to be fought off in exactly the places that matter most.
     */
    public function scopeWithoutUnreviewedIntake(Builder $query): Builder
    {
        return $query->where(function (Builder $outer) {
            $outer->whereDoesntHave('caseFiles', function (Builder $pending) {
                $pending->where('source', CaseFile::SOURCE_SELF_FILED)
                    ->where('status', 'DRAFT')
                    ->where('is_deleted', false);
            })->orWhereHas('caseFiles', function (Builder $reviewed) {
                $reviewed->where('is_deleted', false)
                    ->where(function (Builder $notPending) {
                        $notPending->where('source', '!=', CaseFile::SOURCE_SELF_FILED)
                            ->orWhere('status', '!=', 'DRAFT');
                    });
            });
        });
    }

    /**
     * True when this client is still waiting on intake review — the single-record
     * counterpart to scopeWithoutUnreviewedIntake, for guarding detail routes.
     */
    public function isAwaitingIntakeReview(): bool
    {
        $live = $this->caseFiles()->where('is_deleted', false)->get(['source', 'status']);

        if ($live->isEmpty()) {
            return false;
        }

        return $live->every(fn (CaseFile $case) => $case->source === CaseFile::SOURCE_SELF_FILED
            && $case->status === 'DRAFT');
    }

    /**
     * Singular relationship for the latest case file.
     * Enables eager loading via Client::with('caseFile') and whereHas('caseFile', ...).
     */
    public function caseFile()
    {
        return $this->hasOne(CaseFile::class, 'client_id')
            ->whereRaw('cases.id = (
                SELECT c2.id FROM cases c2
                WHERE c2.client_id = cases.client_id
                AND c2.deleted_at IS NULL
                ORDER BY c2.created_at DESC, c2.id DESC
                LIMIT 1
            )');
    }

    /**
     * Backward-compatible accessor: returns the latest case for this client.
     * Respects eager-loaded relationship when available.
     */
    public function getCaseIdAttribute(): ?string
    {
        return $this->caseFile?->id;
    }

    public function getCaseFileAttribute()
    {
        if ($this->relationLoaded('caseFile')) {
            return $this->getRelation('caseFile');
        }

        return $this->caseFiles()->latest()->first();
    }

    public function addresses()
    {
        return $this->hasMany(ClientAddress::class, 'client_id');
    }

    public function employments()
    {
        return $this->hasMany(ClientEmployment::class, 'client_id');
    }

    public function nextOfKin()
    {
        return $this->hasMany(NextOfKin::class, 'client_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'entity_id')
            ->whereIn('module', ['clients', 'client'])
            ->orderBy('timestamp', 'desc');
    }

    public function relatedAuditLogs()
    {
        $query = AuditLog::where(function ($q) {
            $q->where('entity_id', $this->id)
                ->whereIn('module', ['clients', 'client']);

            if ($this->caseFile) {
                $q->orWhere(function ($sub) {
                    $sub->where('entity_id', $this->caseFile->id)
                        ->whereIn('module', ['CASE', 'cases', 'case_files', 'case']);
                });

                $referralIds = $this->caseFile->referrals()->pluck('id');
                if ($referralIds->isNotEmpty()) {
                    $q->orWhere(function ($sub) use ($referralIds) {
                        $sub->whereIn('entity_id', $referralIds)
                            ->whereIn('module', ['REFERRAL', 'referrals', 'referral']);
                    });
                }
            }
        })
            ->orderBy('timestamp', 'desc')
            ->limit(50);

        return $query->get();
    }
}
