<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public $timestamps = false;

    protected $fillable = [
        'action',
        'module',
        'entity_id',
        'description',
        'old_value',
        'new_value',
        'user_id',
        'timestamp',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'timestamp' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForClient($query, string $clientId, ?string $caseId = null, array $referralIds = [])
    {
        return $query->where(function ($q) use ($clientId, $caseId, $referralIds) {
            $q->where('entity_id', $clientId)
                ->whereIn('module', ['clients']);

            if ($caseId) {
                $q->orWhere(function ($sub) use ($caseId) {
                    $sub->where('entity_id', $caseId)
                        ->whereIn('module', ['CASE', 'cases', 'case_files']);
                });
            }

            if (! empty($referralIds)) {
                $q->orWhere(function ($sub) use ($referralIds) {
                    $sub->whereIn('entity_id', $referralIds)
                        ->whereIn('module', ['REFERRAL', 'referrals']);
                });
            }
        })
            ->orderBy('timestamp', 'desc')
            ->limit(50);
    }
}
