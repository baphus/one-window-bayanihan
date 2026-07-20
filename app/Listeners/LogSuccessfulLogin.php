<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $recent = AuditLog::where('user_id', $event->user->getKey())
            ->where('action', 'LOGIN')
            ->where('timestamp', '>=', now()->subSeconds(5))
            ->exists();

        if ($recent) {
            return;
        }

        AuditLog::create([
            'action' => 'LOGIN',
            'module' => 'auth',
            'entity_id' => $event->user->getKey(),
            'old_value' => null,
            'new_value' => null,
            'user_id' => $event->user->getKey(),
            'timestamp' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'request_id' => request()?->attributes->get('correlation_id')
                ?? request()?->header('X-Request-ID')
                ?? (string) Str::uuid(),
        ]);
    }
}
