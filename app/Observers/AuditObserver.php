<?php

namespace App\Observers;

use App\Casts\EncryptedDate;
use App\Casts\EncryptedString;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\AuditLogFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditObserver
{
    public function created($model): void
    {
        $this->log(AuditAction::CREATE->value, $model, null, $this->filterKeys($model->getAttributes(), $model));
    }

    public function updated($model): void
    {
        $old = array_intersect_key($model->getOriginal(), $model->getDirty());
        $new = $model->getDirty();

        $old = $this->filterKeys($old, $model);
        $new = $this->filterKeys($new, $model);

        unset($old['updated_at'], $new['updated_at']);

        if (empty($new)) {
            return;
        }

        $this->log(AuditAction::UPDATE->value, $model, $old, $new);
    }

    public function deleted($model): void
    {
        $this->log(AuditAction::DELETE->value, $model, $this->filterKeys($model->getAttributes(), $model), null);
    }

    public function restored($model): void
    {
        $this->log(AuditAction::UPDATE->value, $model, ['is_deleted' => true], ['is_deleted' => false]);
    }

    private function log(string $action, $model, ?array $old, ?array $new): void
    {
        // AuditLog itself is not in config('audit.observed_models'), but guard
        // anyway so a future registration cannot recurse infinitely.
        if ($model instanceof AuditLog) {
            return;
        }

        $request = request();

        // Get request ID from LogContext middleware or generate one
        $requestId = $request?->attributes->get('correlation_id')
            ?? $request?->header('X-Request-ID')
            ?? ($request ? (string) Str::uuid() : 'cli');

        // Build data array
        $data = [
            'action' => $action,
            'module' => method_exists($model, 'getAuditModuleName') ? $model->getAuditModuleName() : $model->getTable(),
            'entity_id' => $model->getKey(),
            'entity_label' => method_exists($model, 'getAuditEntityLabel') ? $model->getAuditEntityLabel() : null,
            'old_value' => $this->decryptEncryptedAttributes($old, $model),
            'new_value' => $this->decryptEncryptedAttributes($new, $model),
            'user_id' => Auth::id(),
            'timestamp' => now(),
            'ip_address' => $request?->ip() ?? 'cli',
            'user_agent' => $request?->userAgent() ?? 'cli',
            'request_id' => $requestId,
        ];

        // prev_hash is now computed in AuditLog::boot() creating event —
        // it builds a global SHA-256 chain across all audit logs for
        // integrity verification.

        // Generate description BEFORE creating (single-save pattern)
        try {
            $tempLog = new AuditLog($data);
            $data['description'] = app(AuditLogFormatter::class)->format($tempLog);
        } catch (\Throwable $e) {
            logger()->warning('Audit description generation failed', ['exception' => $e->getMessage()]);
        }

        // Single INSERT
        $log = AuditLog::create($data);
    }

    /**
     * Decrypt EncryptedString/EncryptedDate columns to their real values so
     * the audit trail stays readable. Falls back to the raw stored value
     * when decryption genuinely fails. Null payloads pass through untouched.
     */
    private function decryptEncryptedAttributes(?array $attributes, $model): ?array
    {
        if ($attributes === null || ! method_exists($model, 'getCasts')) {
            return $attributes;
        }

        $casts = $model->getCasts();

        foreach ($attributes as $key => $value) {
            if (! is_string($value) || ! isset($casts[$key])) {
                continue;
            }

            $cast = ltrim(strtok($casts[$key], ':'), '\\');

            if ($cast !== EncryptedString::class && $cast !== EncryptedDate::class) {
                continue;
            }

            try {
                $decrypted = $model->castAttribute($key, $value);
                $attributes[$key] = $decrypted instanceof \DateTimeInterface
                    ? $decrypted->format('Y-m-d')
                    : $decrypted;
            } catch (\Throwable) {
                // Genuinely undecryptable — keep the raw stored value.
            }
        }

        return $attributes;
    }

    private function filterKeys(array $attributes, $model): array
    {
        $excluded = $model::$auditExclude ?? null;
        if (is_array($excluded)) {
            return array_diff_key($attributes, array_flip($excluded));
        }

        return $attributes;
    }
}
