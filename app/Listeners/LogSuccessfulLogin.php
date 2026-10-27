<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $recent = AuditLog::where('user_id', $event->user->getKey())
            ->where('action', AuditAction::LOGIN->value)
            ->where('timestamp', '>=', now()->subSeconds(5))
            ->exists();

        if ($recent) {
            return;
        }

        AuditLogger::login()
            ->byId($event->user->getKey())
            ->forEntity($event->user, $event->user->name)
            ->module(AuditModule::AUTH)
            ->write();
    }
}
