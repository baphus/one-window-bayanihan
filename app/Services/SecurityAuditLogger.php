<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Manual security-category audit entries for events whose model-level diffs
 * are redacted (MFA secrets) or that have no model at all (sessions).
 * Descriptions must never contain secret material.
 */
class SecurityAuditLogger
{
    public static function log(string $module, string $description, ?string $entityId = null, string $action = AuditAction::UPDATE->value): void
    {
        $request = request();

        AuditLog::create([
            'action' => $action,
            'module' => $module,
            'entity_id' => $entityId ?? Auth::id(),
            'description' => $description,
            'user_id' => Auth::id(),
            'timestamp' => now(),
            'ip_address' => $request?->ip() ?? 'cli',
            'user_agent' => $request?->userAgent() ?? 'cli',
            'request_id' => $request?->attributes->get('correlation_id')
                ?? $request?->header('X-Request-ID')
                ?? (string) Str::uuid(),
        ]);
    }
}
