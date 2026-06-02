<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;

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
        ]);
    }
}
