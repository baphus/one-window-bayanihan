<?php

use App\Enums\AuditAction;
use App\Enums\AuditModule;

/*
|--------------------------------------------------------------------------
| Security events
|--------------------------------------------------------------------------
|
| What counts as a "security event" in the audit log.
|
| This exists because AuditModule::SECURITY was never written to: the module
| case was declared, nothing ever called ->module(Security), and filtering the
| audit log on it returns nothing forever. Security-relevant rows are written
| under AUTH, MFA, SESSION and USER (with category=security) instead.
|
| So the definition lives here, as (module, action) pairs, and the Security Log
| page reads it. Adding a row to an existing module requires no change here;
| adding a NEW security action does.
|
| Historical rows are never reclassified: audit_logs is hash-chained
| (prev_hash/chain_seq) and audit:verify runs weekly, so rewriting a row to
| point at a different module would invalidate the chain. This table only
| decides what the page *displays*.
|
*/

return [

    /*
    | Explicit (module => actions) pairs. A row matches when its module and
    | action both appear here.
    */
    'events' => [
        AuditModule::AUTH->value => [
            AuditAction::LOGIN->value,
            AuditAction::LOGOUT->value,
            AuditAction::LOGIN_FAILED->value,
            AuditAction::CHANGE_PASSWORD->value,
            AuditAction::RESET_PASSWORD->value,
            AuditAction::REQUEST_PASSWORD_RESET->value,
            AuditAction::VERIFY_EMAIL->value,
            AuditAction::ACCEPT_INVITE->value,
            AuditAction::REGISTER->value,
        ],

        AuditModule::MFA->value => [
            AuditAction::ENABLE_MFA->value,
            AuditAction::DISABLE_MFA->value,
            AuditAction::REGENERATE_RECOVERY_CODES->value,
            AuditAction::RESET_MFA->value,
            // Failed MFA challenges are recorded here rather than as an
            // anonymous AUTH/LOGIN_FAILED (see MfaChallengeController).
            AuditAction::LOGIN_FAILED->value,
        ],

        AuditModule::USER->value => [
            // Admin-initiated account security actions. Email change and the
            // admin-reset rows also carry category=security; these two are
            // listed by action because their writer does not set a category.
            AuditAction::ADMIN_RESET_PASSWORD->value,
            AuditAction::CHANGE_EMAIL->value,
            AuditAction::ADMIN_CHANGE_EMAIL->value,
            AuditAction::DELETE_ACCOUNT->value,
            AuditAction::PURGE_USER->value,
        ],

        AuditModule::SESSION->value => [
            AuditAction::REVOKE_SESSIONS->value,
        ],
    ],

    /*
    | Rows that match on category alone. Admin password/email/MFA actions are
    | written under module USER with category=security, which is the audit
    | system's own classification — so the page follows it rather than
    | duplicating a list that would drift.
    */
    'categories' => ['security'],

];
