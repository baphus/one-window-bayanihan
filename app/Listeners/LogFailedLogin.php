<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Str;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        // Only the attempted identifier is recorded — never password material.
        // (AuditLog::saving additionally redacts any password/secret/token keys.)
        $email = $event->credentials['email'] ?? null;

        AuditLog::create([
            'action' => 'LOGIN_FAILED',
            'module' => 'auth',
            'entity_id' => $event->user?->getKey(),
            'description' => sprintf('Failed sign-in attempt for %s', $email ?? 'unknown account'),
            'new_value' => ['email' => $email],
            'user_id' => null,
            'timestamp' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'request_id' => request()?->attributes->get('correlation_id')
                ?? request()?->header('X-Request-ID')
                ?? (string) Str::uuid(),
        ]);
    }
}
