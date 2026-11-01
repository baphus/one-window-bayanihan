<?php

namespace App\Listeners;

use App\Enums\AuditModule;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        // A failed MFA challenge also fires Failed (see MfaChallengeController),
        // but it is NOT a failed password attempt and must not be recorded as
        // one: the flag used to be dropped here, so a wrong TOTP code was
        // written as "Failed sign-in attempt for unknown account" with a null
        // email — indistinguishable from a wrong password, and useless to
        // anyone investigating a 2FA brute force.
        if (($event->credentials['mfa'] ?? false) === true) {
            AuditLogger::loginFailed()
                ->byId(null)
                ->forEntity($event->user?->getKey(), $event->user?->email)
                ->module(AuditModule::MFA)
                ->context(null, ['stage' => 'mfa_challenge'])
                ->description('Failed MFA challenge')
                ->write();

            return;
        }

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
