<?php

use App\Enums\AuditAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expands the `audit_logs_action_check` CHECK constraint so every purposeful
 * verb on `AuditAction` is accepted by the database.
 *
 * The constraint previously held the 12 original verbs (CREATE, UPDATE,
 * DELETE, LOGIN, LOGOUT, LOGIN_FAILED, EXPORT, ARCHIVE, UNARCHIVE, PUBLISH,
 * RESTORE, PURGE). The purposeful activity-log work adds ~48 more so each
 * business decision reads as its own verb rather than a generic UPDATE.
 *
 * Postgres cannot ALTER a CHECK constraint in place, so it is dropped and
 * recreated with the full set. The verb list is sourced from
 * `AuditAction::values()` so the enum stays the single source of truth — the
 * parity test in `AuditVocabularyTest` asserts the two never drift apart.
 *
 * The append-only trigger (`trg_audit_logs_append_only`) guards UPDATE/DELETE
 * on rows; this migration is permitted because it runs outside the trigger
 * (DDL, not row writes).
 */
return new class extends Migration
{
    public function up(): void
    {
        $actions = AuditAction::values();

        // Build the quoted, comma-separated verb list for the ANY(ARRAY[...]).
        $quoted = collect($actions)
            ->map(fn (string $verb) => "'".$verb."'::text")
            ->implode(', ');

        DB::statement(
            'ALTER TABLE public.audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check'
        );

        DB::statement(
            'ALTER TABLE public.audit_logs '
            .'ADD CONSTRAINT audit_logs_action_check '
            ."CHECK (((action)::text = ANY (ARRAY[{$quoted}])))"
        );
    }

    public function down(): void
    {
        // Restore the original 12-verb constraint.
        $legacy = "'CREATE'::text, 'UPDATE'::text, 'DELETE'::text, 'LOGIN'::text, "
            ."'LOGOUT'::text, 'LOGIN_FAILED'::text, 'EXPORT'::text, 'ARCHIVE'::text, "
            ."'UNARCHIVE'::text, 'PUBLISH'::text, 'RESTORE'::text, 'PURGE'::text";

        DB::statement(
            'ALTER TABLE public.audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check'
        );

        DB::statement(
            'ALTER TABLE public.audit_logs '
            .'ADD CONSTRAINT audit_logs_action_check '
            ."CHECK (((action)::text = ANY (ARRAY[{$legacy}])))"
        );
    }
};
