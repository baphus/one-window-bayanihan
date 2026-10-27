<?php

namespace App\Listeners;

use App\Enums\AuditModule;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        // Only the attempted identifier is recorded — never password material.
        // (AuditLog::saving additionally redacts any password/secret/token keys.)
        $email = $event->credentials['email'] ?? null;

        AuditLogger::loginFailed()
            ->byId(null)
            ->forEntity($event->user?->getKey())
            ->module(AuditModule::AUTH)
            ->context(null, ['email' => $email])
            ->description(sprintf('Failed sign-in attempt for %s', $email ?? 'unknown account'))
            ->write();
    }
}
