<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        // Guards, session invalidation and "remember me" cookie clearing can
        // each fire the Logout event; only the ones carrying a user are
        // meaningful to audit.
        $user = $event->user;

        if ($user === null) {
            return;
        }

        // Suppress duplicate logout entries from a single teardown (mirrors
        // the LogSuccessfulLogin de-dup window).
        $recent = AuditLog::where('user_id', $user->getKey())
            ->where('action', AuditAction::LOGOUT->value)
            ->where('timestamp', '>=', now()->subSeconds(5))
            ->exists();

        if ($recent) {
            return;
        }

        $request = request();

        AuditLog::create([
            'action' => AuditAction::LOGOUT->value,
            'module' => AuditModule::AUTH->value,
            'entity_id' => $user->getKey(),
            'old_value' => null,
            'new_value' => null,
            'user_id' => $user->getKey(),
            'timestamp' => now(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get('correlation_id')
                ?? $request?->header('X-Request-ID')
                ?? (string) Str::uuid(),
        ]);
    }
}
