<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use App\Services\AuditCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditLog extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public $timestamps = false;

    protected $fillable = [
        'action',
        'module',
        'category',
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

    /**
     * Recursively scrub sensitive values to '[REDACTED]' using the central
     * redaction policy (config/audit.php 'redact'). Both the exact-key list
     * and the substring patterns are matched case-insensitively.
     *
     * A matching key redacts its ENTIRE value — scalar or array — so a
     * sensitive field holding an array (e.g. mfa_recovery_codes => [...]) is
     * fully scrubbed rather than leaking its elements. Non-matching arrays are
     * walked so nested sensitive keys at any depth are still caught. (This is
     * why array_walk_recursive is not used: it visits only scalar leaves and
     * would never see a key whose value is an array.)
     */
    public static function redact(array $value): array
    {
        $keys = array_map('strtolower', (array) config('audit.redact.keys', []));
        $patterns = array_map('strtolower', (array) config('audit.redact.patterns', []));

        $isSensitive = static function ($key) use ($keys, $patterns): bool {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, $keys, true)) {
                return true;
            }

            foreach ($patterns as $pattern) {
                if ($pattern !== '' && str_contains($lowerKey, $pattern)) {
                    return true;
                }
            }

            return false;
        };

        $walk = static function (array $arr) use (&$walk, $isSensitive) {
            foreach ($arr as $key => $val) {
                if ($isSensitive($key)) {
                    $arr[$key] = '[REDACTED]';
                } elseif (is_array($val)) {
                    $arr[$key] = $walk($val);
                }
            }

            return $arr;
        };

        return $walk($value);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $auditLog) {
            // Fail fast on an unrecognised action verb with a clear message,
            // rather than letting the write hit the database CHECK constraint
            // (which raises an opaque SQL error). The stored value is left
            // untouched — action remains a plain string, never an enum object,
            // so the frozen chainDigest() serialisation is unaffected.
            if ($auditLog->action !== null && ! AuditAction::isValid((string) $auditLog->action)) {
                throw new \InvalidArgumentException(
                    "Unknown audit action '{$auditLog->action}'. Use an App\\Enums\\AuditAction value."
                );
            }

            foreach (['old_value', 'new_value'] as $column) {
                $value = $auditLog->$column;
                if (! is_array($value)) {
                    continue;
                }
                $auditLog->$column = self::redact($value);
            }

            // CR/LF sanitization to prevent log injection
            if ($auditLog->description) {
                $auditLog->description = str_replace(["\r", "\n"], ' ', $auditLog->description);
            }

            // Stamp the event category once, centrally, for every write path
            // (observer and manual creates alike).
            if (! $auditLog->category && $auditLog->module && $auditLog->action) {
                $auditLog->category = AuditCategory::for(
                    (string) $auditLog->module,
                    (string) $auditLog->action,
                    $auditLog->user_id
                );
            }
        });

        // Global SHA-256 hash chain for audit integrity verification.
        // Each row stores the hash of the PREVIOUS row's content, forming
        // a tamper-evident chain: any modification to a past row breaks
        // the chain for all subsequent rows. Runs under the advisory lock
        // taken in save(), so the predecessor cannot change before insert.
        static::creating(function (self $auditLog) {
            // chain_seq is the insertion-order key (timestamps are only
            // second-precision and UUIDs don't sort by time).
            $lastLog = AuditLog::orderBy('chain_seq', 'desc')->first();

            if ($lastLog) {
                $auditLog->prev_hash = $lastLog->chainDigest();
            }
            // First row: prev_hash remains null
        });
    }

    /**
     * SHA-256 digest of this row's chain-relevant content; the next row's
     * prev_hash must equal this value. Field list and format are frozen —
     * changing them invalidates verification of all existing rows.
     */
    public function chainDigest(): string
    {
        $content = implode('|', [
            $this->id,
            $this->action,
            $this->module,
            $this->entity_id ?? '',
            $this->user_id ?? '',
            $this->timestamp?->toIso8601String() ?? '',
            json_encode($this->old_value, JSON_UNESCAPED_SLASHES),
            json_encode($this->new_value, JSON_UNESCAPED_SLASHES),
            $this->ip_address ?? '',
            $this->prev_hash ?? '',
        ]);

        return hash('sha256', $content);
    }

    /**
     * Serialize chain construction: concurrent inserts must not both read
     * the same predecessor. The transactional advisory lock releases at
     * commit; inside an outer transaction it is held until that commits,
     * which is acceptable at this write volume.
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            DB::statement("SELECT pg_advisory_xact_lock(hashtext('audit_log_chain'))");

            return parent::save($options);
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
