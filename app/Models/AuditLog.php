<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        'ip_address',
        'user_agent',
        'request_id',
        'prev_hash',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'timestamp' => 'datetime',
        'is_deleted' => 'boolean',
        'request_id' => 'string',
    ];

    private static array $sensitiveFields = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'mfa_enabled_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $auditLog) {
            foreach (['old_value', 'new_value'] as $column) {
                $value = $auditLog->$column;
                if (! is_array($value)) {
                    continue;
                }
                array_walk_recursive($value, function (&$v, $k) {
                    // Exact match on known sensitive fields
                    if (in_array($k, self::$sensitiveFields, true)) {
                        $v = '[REDACTED]';

                        return;
                    }
                    // Pattern match: any key containing password/secret/token
                    $lowerKey = strtolower($k);
                    if (str_contains($lowerKey, 'password') ||
                        str_contains($lowerKey, 'secret') ||
                        str_contains($lowerKey, 'token')) {
                        $v = '[REDACTED]';
                    }
                });
                $auditLog->$column = $value;
            }

            // CR/LF sanitization to prevent log injection
            if ($auditLog->description) {
                $auditLog->description = str_replace(["\r", "\n"], ' ', $auditLog->description);
            }
        });

        // Global SHA-256 hash chain for audit integrity verification.
        // Each row stores the hash of the PREVIOUS row's content, forming
        // a tamper-evident chain: any modification to a past row breaks
        // the chain for all subsequent rows.
        static::creating(function (self $auditLog) {
            $lastLog = AuditLog::orderBy('timestamp', 'desc')->first();

            if ($lastLog) {
                $content = implode('|', [
                    $lastLog->id,
                    $lastLog->action,
                    $lastLog->module,
                    $lastLog->entity_id ?? '',
                    $lastLog->user_id ?? '',
                    $lastLog->timestamp?->toIso8601String() ?? '',
                    json_encode($lastLog->old_value, JSON_UNESCAPED_SLASHES),
                    json_encode($lastLog->new_value, JSON_UNESCAPED_SLASHES),
                    $lastLog->ip_address ?? '',
                    $lastLog->prev_hash ?? '',
                ]);

                $auditLog->prev_hash = hash('sha256', $content);
            }
            // First row: prev_hash remains null
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForClient($query, string $clientId, ?string $caseId = null, array $referralIds = [])
    {
        return $query->where(function ($q) use ($clientId, $caseId, $referralIds) {
            $q->where('entity_id', $clientId)
                ->whereIn('module', ['clients', 'client']);

            if ($caseId) {
                $q->orWhere(function ($sub) use ($caseId) {
                    $sub->where('entity_id', $caseId)
                        ->whereIn('module', ['CASE', 'cases', 'case_files', 'case']);
                });
            }

            if (! empty($referralIds)) {
                $q->orWhere(function ($sub) use ($referralIds) {
                    $sub->whereIn('entity_id', $referralIds)
                        ->whereIn('module', ['REFERRAL', 'referrals', 'referral']);
                });
            }
        })
            ->orderBy('timestamp', 'desc')
            ->limit(50);
    }
}
