<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Mail\EmailChangedNotification;
use App\Mail\UserInviteMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserService
{
    /**
     * SQLSTATEs that mean "a row still references this user".
     *
     * Postgres splits the one condition across two codes: 23001
     * (restrict_violation) comes from the immediate trigger an `ON DELETE
     * RESTRICT` constraint installs, and 23503 (foreign_key_violation) comes
     * from the deferred check an `ON DELETE NO ACTION` constraint performs at
     * end of statement. Matching only 23503 lets every RESTRICT column — which
     * is every inbound reference into `users(id)` that matters here — escape
     * as a raw 500.
     */
    private const REFERENCE_VIOLATION_SQLSTATES = ['23001', '23503'];

    public function __construct(
        private readonly ReferenceDataService $referenceData,
    ) {}

    public function createUser(array $data, ?string $actorId = null): User
    {
        return DB::transaction(function () use ($data, $actorId) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'agcy_id' => $this->resolveAgencyId($data),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            AuditLog::create([
                'action' => AuditAction::CREATE->value,
                'module' => AuditModule::USER->value,
                'entity_id' => $user->id,
                'new_value' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
                'description' => 'User created directly by administrator',
                'user_id' => $actorId ?? auth()->id(),
                'timestamp' => now(),
            ]);

            return $user;
        });
    }

    public function pendingInviteFor(string $email): ?UserInvite
    {
        return UserInvite::where('email', $email)
            ->whereNull('consumed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @return array{invite: UserInvite, token: string}
     */
    public function inviteUser(array $data, string $actorId): array
    {
        $result = DB::transaction(function () use ($data, $actorId) {
            $token = Str::random(64);

            $invite = UserInvite::create([
                'email' => $data['email'],
                'role' => $data['role'],
                'agcy_id' => $this->resolveAgencyId($data),
                'token' => null,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
                'created_by' => $actorId,
            ]);

            return ['invite' => $invite, 'token' => $token];
        });

        Mail::to($data['email'])->queue(new UserInviteMail($result['invite'], $result['token']));

        return $result;
    }

    public function resendInvite(UserInvite $invite): UserInvite
    {
        $token = Str::random(64);

        $fresh = DB::transaction(function () use ($invite, $token) {
            $locked = $this->lockInvite($invite);

            if ($locked->isConsumed() || $locked->isCancelled()) {
                throw new LogicException('This invite can no longer be resent.');
            }

            $locked->update([
                'token' => null,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
                'consumed_at' => null,
                'cancelled_at' => null,
            ]);

            return $locked;
        });

        Mail::to($fresh->email)->queue(new UserInviteMail($fresh->refresh(), $token));

        return $fresh;
    }

    public function cancelInvite(UserInvite $invite): UserInvite
    {
        return DB::transaction(function () use ($invite) {
            $locked = $this->lockInvite($invite);

            if ($locked->isConsumed()) {
                throw new LogicException('This invite has already been used.');
            }

            $locked->update(['cancelled_at' => now()]);

            return $locked;
        });
    }

    /**
     * Whether an update payload would actually move the account to a different address.
     *
     * The admin users form always submits `email`, so only a genuine difference
     * counts. This is the single definition that both `updateUser()`'s OTP gate
     * and the controller's re-auth/throttle decision rely on, so the two cannot
     * drift apart into a bypass.
     *
     * @param  array<string, mixed>  $data
     */
    public function willChangeEmail(User $user, array $data): bool
    {
        return isset($data['email']) && $user->email !== $data['email'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  string|null  $verifiedNewEmail  Address proven by the admin email-change
     *                                         OTP flow. Required — and matched against
     *                                         the submitted value — whenever $data
     *                                         moves the account to a new address.
     *
     * @throws ValidationException when an email change arrives without a matching
     *                             OTP verification.
     */
    public function updateUser(User $user, array $data, ?string $actorId = null, ?string $verifiedNewEmail = null): User
    {
        $emailChangeMail = null;
        $passwordChanged = false;

        $user = DB::transaction(function () use ($user, $data, $actorId, $verifiedNewEmail, &$emailChangeMail, &$passwordChanged) {
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
                $passwordChanged = true;
            } else {
                unset($data['password']);
            }

            $emailChanged = $this->willChangeEmail($user, $data);

            // The OTP proof is what makes an admin-driven email change safe: it
            // shows the admin controls the destination mailbox, so nobody can
            // repoint a colleague's account (and any notification bound to it)
            // by editing one field. Anything without a matching verification —
            // including an absent proof or one issued for a different address —
            // rolls the transaction back rather than silently skipping the step.
            if ($emailChanged && ($verifiedNewEmail === null || ! hash_equals($verifiedNewEmail, (string) $data['email']))) {
                throw ValidationException::withMessages([
                    'email' => 'Changing this email requires verifying the new address first.',
                ]);
            }

            if ($emailChanged) {
                $oldEmail = $user->email;
                $user->email_verified_at = now();
                $user->email = $data['email'];
                $user->save();

                AuditLog::create([
                    'action' => AuditAction::UPDATE->value,
                    'module' => AuditModule::USER->value,
                    // Account-credential change: must appear in the Security view
                    // (matches EmailChangeController).
                    'category' => AuditCategory::SECURITY,
                    'entity_id' => $user->id,
                    'old_value' => ['email' => $oldEmail],
                    'new_value' => ['email' => $data['email']],
                    'description' => 'Email changed from '.$oldEmail.' to '.$data['email'].' by administrator',
                    'user_id' => $actorId ?? auth()->id(),
                    'timestamp' => now(),
                ]);

                $emailChangeMail = [
                    'oldEmail' => $oldEmail,
                    'newEmail' => $data['email'],
                    'userName' => $user->name,
                ];
            }

            unset($data['email']);
            $user->update($data);

            // A password-only edit writes nothing the audit observer keeps:
            // User::$auditExclude drops `password` and `remember_token`, and
            // the row carries no other dirty column. Left alone, an admin
            // resetting a colleague's password — a full account takeover —
            // would evict that colleague's live sessions with no attributable
            // audit entry, which is exactly what the removed Active Sessions
            // page used to leave behind. Log the credential change explicitly,
            // the same way the email-change branch above does.
            if ($passwordChanged) {
                AuditLog::create([
                    'action' => AuditAction::UPDATE->value,
                    'module' => AuditModule::USER->value,
                    'category' => AuditCategory::SECURITY,
                    'entity_id' => $user->id,
                    'old_value' => null,
                    'new_value' => null,
                    'description' => 'Password was reset by administrator',
                    'user_id' => $actorId ?? auth()->id(),
                    'timestamp' => now(),
                ]);
            }

            return $user;
        });

        if ($passwordChanged) {
            // Credential change: every existing login must die, including
            // remember-me cookies. Runs after the transaction commits so the
            // admin's own session is not disturbed mid-write.
            $this->invalidateSessions($user);
        }

        if ($emailChangeMail !== null) {
            Mail::to($emailChangeMail['oldEmail'])->queue(
                new EmailChangedNotification(
                    $emailChangeMail['oldEmail'],
                    $emailChangeMail['newEmail'],
                    $emailChangeMail['userName'],
                )
            );
        }

        return $user;
    }

    /**
     * Deactivate an account by soft-deleting it.
     *
     * This goes through the model's own soft-delete mechanism rather than
     * writing `is_deleted` by hand. `SoftDeleteFlag::runSoftDelete()` is what
     * keeps `is_deleted`, `deleted_by` and `deleted_at` in agreement, and the
     * `deleted_at` it writes is the only one of the three the `SoftDeletes`
     * global scope looks at — a flag-only deactivate left `deleted_at` null, so
     * every query that relied on the scope alone (agency rosters, counts,
     * `withCount` relations) went on treating a deactivated colleague as a live
     * account. A route model binding over `{user}` hid them; nothing else did.
     *
     * `is_active` is staged before the delete because `runSoftDelete()` flushes
     * whatever is dirty in the same single UPDATE.
     */
    public function deactivate(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->is_active = false;
            $user->delete();

            $this->invalidateSessions($user);

            return $user;
        });
    }

    /**
     * Permanently remove a user row.
     *
     * `users(id)` carries 38 inbound foreign keys. Postgres settles the ones
     * the schema already declared disposable — `ON DELETE CASCADE`
     * (agency_thread_reads.user_id, generated_documents.user_id,
     * user_invites.created_by) and `ON DELETE SET NULL` (cases.reviewed_by,
     * clients.user_id, referral_client_access_links.revoked_by,
     * referral_client_message_attachments.deleted_by) — as part of the DELETE
     * itself, so there is nothing for this method to release on those.
     *
     * What is left are the `ON DELETE RESTRICT` columns, and every one of them
     * is an attribution pointer: who owns this case, who uploaded this
     * document, who soft-deleted that row. `audit_logs.user_id` is the hard
     * blocker. `AuditLog::chainDigest()` hashes `user_id` into its frozen field
     * list, and `trg_audit_logs_append_only` rejects UPDATE/DELETE on the table
     * unless `app.allow_audit_mutations` is set — so the column can neither be
     * re-pointed at a tombstone user nor NULLed (it would serialise as '' in
     * the digest). Either would change that row's digest *and* every digest
     * after it, and `audit:verify` would report a break at that point. There is
     * no anonymisation that preserves the chain, because the anonymised id is
     * itself chain content.
     *
     * So the delete is refused, not forced through. The preflight below names
     * the one reference that can be counted cheaply and precisely; any other
     * constraint the DELETE trips is resolved out of the live catalog and
     * reported the same way. The endpoint degrades to a validation message in
     * every case — a raw QueryException would have been a 500.
     *
     * An account that has never been acted on — never logged in, no cases, no
     * documents, nothing deleted on its behalf — has no RESTRICT references
     * and still deletes outright.
     *
     * @throws ValidationException when linked records make the delete unsafe.
     */
    public function forceDelete(User $user): void
    {
        try {
            DB::transaction(function () use ($user) {
                // Re-read under a row lock. `FOR UPDATE` conflicts with the `FOR
                // KEY SHARE` that inserting a row with a foreign key takes, so no
                // new reference can appear between the preflight and the DELETE.
                $locked = User::withTrashed()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                $auditEntries = AuditLog::where('user_id', $locked->id)->count();

                if ($auditEntries > 0) {
                    $entries = $auditEntries === 1 ? 'entry is' : 'entries are';

                    throw ValidationException::withMessages([
                        'user' => "Cannot permanently delete {$locked->name}: {$auditEntries} audit trail {$entries}"
                            .' attributed to this account. The audit hash chain covers those rows, so they cannot be'
                            .' re-pointed without breaking verification of every entry written after them. Deactivate the'
                            .' account instead.',
                    ]);
                }

                $this->invalidateSessions($locked);

                $locked->forceDelete();
            });
        } catch (QueryException $e) {
            // A RESTRICT column is rejected by an immediate trigger and
            // surfaces as 23001 (restrict_violation); 23503
            // (foreign_key_violation) is what a NO ACTION column raises at
            // end of statement. Both mean the same thing here — the account
            // is still referenced — and the DELETE is the only write in the
            // closure that can raise either, so every other SQLSTATE is a
            // genuine fault and still propagates. Postgres has already rolled
            // this transaction back by the time control gets here, so the
            // connection is usable again and the constraint can be resolved.
            if (! in_array($e->getCode(), self::REFERENCE_VIOLATION_SQLSTATES, true)) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'user' => $this->explainBlockedDelete($e, $user),
            ]);
        }
    }

    /**
     * Turn a foreign-key violation on `users(id)` into an actionable message.
     *
     * The constraint name is read back out of the driver's error text and
     * resolved against `pg_constraint`, so a reference added to the schema
     * after this method was written still produces a named table and column
     * instead of a generic refusal — there is no hardcoded table list here to
     * go stale.
     */
    private function explainBlockedDelete(QueryException $e, User $user): string
    {
        $constraint = $this->foreignKeyConstraintName($e);
        $reference = $constraint !== null ? $this->resolveForeignKeyColumn($constraint) : null;

        if ($reference === null) {
            return "Cannot permanently delete {$user->name}: this account is still referenced by linked records."
                .' Deactivate the account instead.';
        }

        [$table, $column] = $reference;

        $count = DB::table($table)->where($column, $user->id)->count();
        $records = $count === 1 ? 'record' : 'records';
        $pronoun = $count === 1 ? 'it' : 'them';

        return "Cannot permanently delete {$user->name}: {$count} {$records} still reference this account through"
            ." {$table}.{$column}, and dropping {$pronoun} would remove that attribution."
            .' Deactivate the account instead.';
    }

    /**
     * Pull the constraint name back out of the driver's error text.
     *
     * Matched on the `foreign key constraint "<name>"` tail rather than on the
     * words before it: Postgres words the two cases differently — a NO ACTION
     * column reads `violates foreign key constraint "x"`, a RESTRICT column
     * reads `violates RESTRICT setting of foreign key constraint "x" on table
     * "y"` — so a prefix-anchored pattern silently misses the RESTRICT half,
     * which is the half this method exists for.
     */
    private function foreignKeyConstraintName(QueryException $e): ?string
    {
        if (! preg_match('/foreign key constraint "([^"]+)"/', $e->getMessage(), $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array{0: string, 1: string}|null [table, column], or null when the
     *                                          constraint is unknown or is not a
     *                                          reference into `users`.
     */
    private function resolveForeignKeyColumn(string $constraint): ?array
    {
        $row = DB::selectOne(
            <<<'SQL'
                SELECT c.conrelid::regclass::text AS table_name,
                       (SELECT a.attname
                          FROM pg_attribute a
                         WHERE a.attrelid = c.conrelid
                           AND a.attnum = ANY (c.conkey)) AS column_name
                  FROM pg_constraint c
                 WHERE c.conname = ?
                   AND c.contype = 'f'
                   AND c.confrelid = 'users'::regclass
                 LIMIT 1
                SQL,
            [$constraint],
        );

        if ($row === null || $row->column_name === null) {
            return null;
        }

        return [$row->table_name, $row->column_name];
    }

    /**
     * Bring a deactivated account back.
     *
     * `deleted_by` is cleared here rather than left to the `restoring` hook:
     * this path writes the columns directly instead of calling `restore()`, so
     * the hook never runs and the row would keep attributing the reactivation
     * to whoever performed the original delete.
     */
    public function reactivate(User $user, ?string $actorId = null): User
    {
        return DB::transaction(function () use ($user, $actorId) {
            $user->is_active = true;
            $user->is_deleted = false;
            $user->deleted_at = null;
            $user->deleted_by = null;
            $user->save();

            AuditLog::create([
                'action' => AuditAction::UPDATE->value,
                'module' => AuditModule::USER->value,
                'entity_id' => $user->id,
                'user_id' => $actorId ?? auth()->id(),
                'timestamp' => now(),
            ]);

            return $user;
        });
    }

    public function toggleVerification(User $user): User
    {
        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        return $user;
    }

    public function resetMfa(User $target, User $admin): User
    {
        return DB::transaction(function () use ($target, $admin) {
            $target->mfa_secret = null;
            $target->mfa_recovery_codes = null;
            $target->mfa_enabled_at = null;
            $target->save();

            // Kill active sessions for the target user to force re-login
            $this->invalidateSessions($target);

            SecurityAuditLogger::log('mfa', sprintf('%s admin-reset MFA for %s', $admin->name, $target->name));

            return $target;
        });
    }

    /**
     * End every existing login for the user.
     *
     * Two mechanisms, because "logged in" has two halves, and only one of them
     * is driver-independent:
     *
     *  - `users.remember_token` is rotated on every driver. A "remember me"
     *    cookie re-authenticates straight from that column with no session
     *    record involved, so rotating it kills every outstanding recaller
     *    everywhere — the one guarantee this method can make unconditionally.
     *  - The `sessions` rows are deleted, which only ends an already
     *    established session when `session.driver` is `database`. Under
     *    redis/memcached/file/dynamic the live sessions live in that store, so
     *    the delete matches nothing useful and a session captured before the
     *    credential change keeps working until it expires. Sessions carry no
     *    version stamp, so this cannot be detected after the fact; the
     *    non-database case is logged instead.
     *
     * Callers therefore get "no remembered device survives this call" always,
     * and "no other live session survives this call" only on the database
     * driver. Anything that must terminate live sessions on every driver
     * needs a session store that can be enumerated per user, not this method.
     */
    public function invalidateSessions(User $user): void
    {
        $sessionDriver = config('session.driver');

        if ($sessionDriver !== 'database') {
            Log::warning('UserService::invalidateSessions: non-database session driver, live sessions were not invalidated', [
                'session_driver' => $sessionDriver,
                'user_id' => $user->id,
            ]);
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $user->setRememberToken(Str::random(60));
        $user->save();
    }

    private function resolveAgencyId(array $data): ?string
    {
        $agcyId = $data['agcy_id'] ?? null;

        if (! $agcyId && ($data['role'] ?? null) === UserRole::AGENCY->value) {
            $agcyId = $this->referenceData->getDefaultAgency()?->id;
        }

        return $agcyId;
    }

    /**
     * Re-read an invite under a row lock inside the current transaction.
     *
     * Callers hold a model that may already be stale: the admin controllers
     * check the invite's state and then hand it over, while a registration can
     * consume it concurrently. Locking the row and returning the freshly read
     * state lets the transition check the authoritative values instead of the
     * caller's copy. Must be called from within a transaction, otherwise
     * `FOR UPDATE` has no effect.
     */
    private function lockInvite(UserInvite $invite): UserInvite
    {
        return UserInvite::whereKey($invite->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
