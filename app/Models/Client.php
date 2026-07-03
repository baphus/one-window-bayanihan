<?php

namespace App\Models;

use App\Models\Concerns\HasAvatar;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasAvatar, HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by'];

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
        'date_of_birth' => 'date',
        'is_deleted' => 'boolean',
    ];

    public function caseFiles()
    {
        return $this->hasMany(CaseFile::class, 'client_id');
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
