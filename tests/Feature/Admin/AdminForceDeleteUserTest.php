<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Http\Middleware\CheckUserActive;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Permanently delete user" used to 500 for every staff account.
 *
 * `users(id)` carries dozens of inbound `ON DELETE RESTRICT` foreign keys, and
 * every staff user owns audit entries by definition, so the unguarded
 * `$user->forceDelete()` always tripped one of them and surfaced a raw
 * QueryException as a 500. These pin the two properties that matter now:
 * the delete either succeeds or is refused with an actionable message, and it
 * is never a 500.
 *
 * Refusing is the correct outcome for an account with audit history, not a
 * fallback: `AuditLog::chainDigest()` hashes `user_id` into its frozen field
 * list and `trg_audit_logs_append_only` rejects UPDATE/DELETE unless
 * `app.allow_audit_mutations` is set, so re-pointing or NULLing that column
 * would change that row's digest and every digest after it — `audit:verify`
 * would report a break. An account nobody ever acted on still deletes outright.
 */
class AdminForceDeleteUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
    }

    /**
     * A deactivated staff account — the state the destroy route treats as
     * "purge this permanently" rather than "deactivate".
     *
     * @param  array<string, mixed>  $attributes
     */
    private function purgedUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::CASE_MANAGER->value,
            'is_active' => false,
        ], $attributes));
    }

    private function auditEntryFor(User $user, string $description): AuditLog
    {
        return AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'case',
            'description' => $description,
            'user_id' => $user->id,
            'timestamp' => now(),
            'ip_address' => '127.0.0.1',
        ]);
    }

    private function sessionRowFor(User $user): string
    {
        $id = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        return $id;
    }

    private function destroyMessage(): string
    {
        $errors = session('errors');

        $this->assertNotNull($errors, 'Expected a validation error bag on the redirect.');

        return (string) $errors->first('user');
    }

    public function test_account_with_audit_trail_is_refused_with_a_clear_message_and_no_500(): void
    {
        $target = $this->purgedUser(['name' => 'Audited Colleague']);
        $first = $this->auditEntryFor($target, 'Reviewed intake');
        $second = $this->auditEntryFor($target, 'Closed case');

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target->id));

        $this->assertFalse($response->isServerError(), 'The purge must never surface a 500.');
        $response->assertRedirect();
        $response->assertSessionHasErrors('user');

        $message = $this->destroyMessage();
        $this->assertStringContainsString('Cannot permanently delete', $message);
        $this->assertStringContainsString('2 audit trail entries', $message);
        $this->assertStringContainsString('Deactivate the account instead', $message);

        // Refused means refused: the row is still there and its audit trail is
        // untouched, attribution included.
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $first->id,
            'user_id' => $target->id,
            'prev_hash' => $first->prev_hash,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $second->id,
            'user_id' => $target->id,
            'prev_hash' => $second->prev_hash,
        ]);
    }

    public function test_refused_purge_leaves_the_audit_hash_chain_verifiable(): void
    {
        $target = $this->purgedUser();
        $this->auditEntryFor($target, 'Reviewed intake');
        $trailing = $this->auditEntryFor($this->admin, 'Admin activity afterwards');

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertSessionHasErrors('user');

        // The refusal rewrote nothing, so the chain — including the entry
        // written after the target's — must still verify end to end.
        $this->artisan('audit:verify')->assertExitCode(0);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $trailing->id,
            'prev_hash' => $trailing->prev_hash,
        ]);
    }

    public function test_other_restricted_reference_is_reported_by_table_and_column(): void
    {
        $target = $this->purgedUser();
        // No audit entry attributes anything to this account, so the only
        // thing left to block the DELETE is an ordinary ownership reference.
        CaseFile::factory()->create(['user_id' => $target->id]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target->id));

        $this->assertFalse($response->isServerError(), 'A blocked FK must never surface a 500.');
        $response->assertRedirect();
        $response->assertSessionHasErrors('user');

        $message = $this->destroyMessage();
        $this->assertStringContainsString('cases.user_id', $message);
        $this->assertStringContainsString('Deactivate the account instead', $message);

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('cases', ['user_id' => $target->id]);
    }

    public function test_unreferenced_account_is_purged_and_its_sessions_are_gone(): void
    {
        $target = $this->purgedUser();
        $sessionId = $this->sessionRowFor($target);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $target->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        // Sessions must die before the row does — there is no user left to
        // rotate a remember token on afterwards.
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);

        $this->artisan('audit:verify')->assertExitCode(0);
    }

    public function test_non_admin_cannot_purge_a_user(): void
    {
        $caseManager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $target = $this->purgedUser();

        $this->actingAs($caseManager)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_last_active_admin_is_never_purged(): void
    {
        // The guard counts only ACTIVE administrators: an account that is
        // already inactive provides no usable admin, so it cannot be what
        // protects the app from ending up without one. Here the actor is an
        // inactive admin (the role check still passes — users.role is ADMIN —
        // while CheckUserActive is bypassed the way an expired session would
        // be) and the target is the only active admin, so the purge must be
        // refused. See AdminCrudLowsTest for the matching guard coverage.
        $lastActiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => true,
        ]);
        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => false,
        ]);

        // setUp() creates an active admin; step it down so the target really is
        // the only active administrator left in the table.
        $this->admin->forceFill(['is_active' => false])->saveQuietly();

        $response = $this->withoutMiddleware([CheckUserActive::class])
            ->actingAs($inactiveAdmin)
            ->delete(route('admin.users.destroy', $lastActiveAdmin->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error', 'Cannot delete the only admin user.');

        $this->assertDatabaseHas('users', ['id' => $lastActiveAdmin->id]);
    }

    public function test_inactive_admin_is_purged_while_an_active_admin_remains(): void
    {
        // Same guard, other side of it: the target is an admin account that is
        // already inactive and soft-deleted, and the acting admin is active.
        // The inactive account never counted toward "the last admin" — it
        // cannot be acted as — so purging it leaves a usable administrator in
        // place and is allowed to complete.
        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN->value,
            'is_active' => false,
            'is_deleted' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $inactiveAdmin->id));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $inactiveAdmin->id]);
    }
}
