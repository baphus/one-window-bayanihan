<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\AuditLogFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            'old_value' => $old,
            'new_value' => $new,
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

        // Invalidate cache for distinct action/module lists
        Cache::forget('audit_log_available_actions');
        Cache::forget('audit_log_available_modules');
    }

    private function filterKeys(array $attributes, $model): array
    {
        if (property_exists($model, 'auditExclude')) {
            return array_diff_key($attributes, array_flip($model::$auditExclude));
        }

        return $attributes;
    }
}
