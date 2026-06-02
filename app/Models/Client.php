<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by'];

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
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
     * Backward-compatible accessor: returns the latest case for this client.
     * Used where existing code still references $client->caseFile (singular).
     */
    public function getCaseFileAttribute()
    {
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

    public function getAvatarUrlAttribute(?string $value): ?string
    {
        if ($value) {
            return Storage::url($value);
        }

        return null;
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'entity_id')
            ->whereIn('module', ['clients'])
            ->orderBy('timestamp', 'desc');
    }

    public function relatedAuditLogs()
    {
        $query = AuditLog::where(function ($q) {
            $q->where('entity_id', $this->id)
                ->whereIn('module', ['clients']);

            if ($this->caseFile) {
                $q->orWhere(function ($sub) {
                    $sub->where('entity_id', $this->caseFile->id)
                        ->whereIn('module', ['CASE', 'cases', 'case_files']);
                });

                $referralIds = $this->caseFile->referrals()->pluck('id');
                if ($referralIds->isNotEmpty()) {
                    $q->orWhere(function ($sub) use ($referralIds) {
                        $sub->whereIn('entity_id', $referralIds)
                            ->whereIn('module', ['REFERRAL', 'referrals']);
                    });
                }
            }
        })
            ->orderBy('timestamp', 'desc')
            ->limit(50);

        return $query->get();
    }
}
