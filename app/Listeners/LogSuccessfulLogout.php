<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Logout;

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

        AuditLogger::logout()
            ->byId($user->getKey())
            ->forEntity($user, $user->name)
            ->module(AuditModule::AUTH)
            ->write();
    }
}
