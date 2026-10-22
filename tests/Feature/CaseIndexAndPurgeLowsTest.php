<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\Client;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Batch-3 low-severity contract pins:
 *
 * - ClientController::index must not ship a dead `users` prop (the page never
 *   renders it) — it cost a query on every directory load.
 * - CaseController::create/intake must not ship dead producer props
 *   (`linkedOfws`, `filerIsNextOfKin`, `categories`, `caseIssues`).
 * - cases:purge-trashed must reject a negative/garbage/zero --days instead of
 *   silently flipping the retention cutoff into the future; the service-side
 *   purge must refuse a retention below 1 day on its own as well.
 * - case-issues.quick must lose the create race to the unique constraint with
 *   a validation error, not a 500.
 */
class CaseIndexAndPurgeLowsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed> The Inertia client-index props.
     */
    private function clientIndexProps(array $params = []): array
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);

        $response = $this->actingAs($user)->get(route('clients.index', $params));

        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    private function trashedCase(User $manager): CaseFile
    {
        $client = Client::factory()->create(['user_id' => User::factory()->create(['role' => UserRole::OFW->value])->id]);
        $case = CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);
        $case->delete();
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);

        return $case;
    }

    public function test_client_index_payload_lacks_the_dead_users_prop(): void
    {
        $props = $this->clientIndexProps();

        $this->assertArrayNotHasKey('users', $props);
        // The props the page actually renders stay put.
        $this->assertArrayHasKey('clients', $props);
        $this->assertArrayHasKey('agencies', $props);
        $this->assertArrayHasKey('categories', $props);
        $this->assertArrayHasKey('caseIssues', $props);
    }

    public function test_case_create_payload_lacks_the_dead_ofw_keys(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);

        $response = $this->actingAs($manager)->get(route('cases.create'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertArrayNotHasKey('linkedOfws', $props);
        $this->assertArrayNotHasKey('filerIsNextOfKin', $props);
        // The consumed props stay.
        $this->assertArrayHasKey('client', $props);
        $this->assertArrayHasKey('categories', $props);
        $this->assertArrayHasKey('caseIssues', $props);
        $this->assertArrayHasKey('occupationOptions', $props);

        // The ?client_id= prefill arm keeps working without the dead keys.
        $farOfw = User::factory()->create(['role' => UserRole::OFW->value]);
        $client = Client::factory()->create(['user_id' => $farOfw->id]);

        $response = $this->actingAs($manager)->get(route('cases.create', ['client_id' => $client->id]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertArrayNotHasKey('linkedOfws', $props);
        $this->assertArrayNotHasKey('filerIsNextOfKin', $props);
        $this->assertSame($client->id, $props['client']['id']);
    }

    public function test_ofw_intake_payload_lacks_the_reference_props(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);

        $response = $this->actingAs($ofw)->get(route('ofw.intake'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertArrayNotHasKey('categories', $props);
        $this->assertArrayNotHasKey('caseIssues', $props);
        // The consumed props stay.
        $this->assertArrayHasKey('profile', $props);
        $this->assertArrayHasKey('vulnerabilityOptions', $props);
    }

    public function test_purge_command_rejects_a_negative_days_option_without_purging(): void
    {
        $case = $this->trashedCase(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));

        $exit = \Artisan::call('cases:purge-trashed', ['--days' => '-3']);

        $this->assertSame(1, $exit, 'a negative --days must fail the command');
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);
    }

    public function test_purge_command_rejects_a_zero_days_option_without_purging(): void
    {
        $case = $this->trashedCase(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));

        // A 0-day retention flips the cutoff to "now" and would purge every
        // trashed case in one run: it must fail the command, not the policy.
        $exit = \Artisan::call('cases:purge-trashed', ['--days' => '0']);

        $this->assertSame(1, $exit, 'a zero --days must fail the command');
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);
    }

    public function test_purge_command_rejects_a_non_integer_days_option(): void
    {
        $case = $this->trashedCase(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));

        $exit = \Artisan::call('cases:purge-trashed', ['--days' => 'abc']);

        $this->assertSame(1, $exit, 'a non-integer --days must fail the command, not cast to 0');
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);
    }

    public function test_purge_service_guard_aborts_on_negative_retention(): void
    {
        $case = $this->trashedCase(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));

        $purged = app(CaseService::class)->purgeTrashedCases(-1);

        $this->assertSame(0, $purged);
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);
    }

    public function test_purge_service_guard_aborts_on_zero_retention(): void
    {
        $case = $this->trashedCase(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));

        // Defensive guard: even if the command's validation were bypassed, the
        // service may not treat a 0-day retention as "purge everything now".
        $purged = app(CaseService::class)->purgeTrashedCases(0);

        $this->assertSame(0, $purged);
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'is_deleted' => true]);
    }

    public function test_purge_still_runs_for_a_valid_retention(): void
    {
        $case = $this->trashedCase(User::factory()->create(['role' => UserRole::CASE_MANAGER->value]));
        DB::table('cases')->where('id', $case->id)->update(['deleted_at' => now()->subDays(400)]);

        $exit = \Artisan::call('cases:purge-trashed', ['--days' => '200']);

        $this->assertSame(0, $exit);
        $this->assertDatabaseMissing('cases', ['id' => $case->id]);
    }

    public function test_quick_store_creates_an_issue_then_rejects_a_duplicate_name(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        // postJson: a quick-add is an XHR call; without the JSON accept, a
        // 422 comes back as a redirect instead of a JSON error payload.
        $response = $this->actingAs($manager)->postJson(route('case-issues.quick'), ['name' => 'Lost Work Permit']);

        $response->assertOk();
        $this->assertSame('Lost Work Permit', $response->json('name'));
        $this->assertSame(1, CaseIssue::where('name', 'Lost Work Permit')->count());

        // Second call while the row exists: the unique rule fires with the
        // same UX, no duplicate row.
        $response = $this->actingAs($manager)->postJson(route('case-issues.quick'), ['name' => 'Lost Work Permit']);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('errors.name.0'));
        $this->assertSame(1, CaseIssue::where('name', 'Lost Work Permit')->count());
    }

    public function test_quick_store_loses_the_create_race_to_the_unique_key_gracefully(): void
    {
        // Simulate the concurrent request that wins: its row lands between
        // validation and the create call (the window the validation rule
        // cannot see). quickStore must return the validation-error UX, not a
        // 500 — and no duplicate may remain.
        $raced = false;
        CaseIssue::creating(function (CaseIssue $issue) use (&$raced) {
            if ($raced) {
                return true;
            }
            $raced = true;

            DB::table('case_issues')->insert([
                'id' => (string) Str::uuid(),
                'name' => $issue->name,
                'sort_order' => (int) (DB::table('case_issues')->max('sort_order') ?? 0) + 1,
                'is_active' => true,
                'is_deleted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $manager = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $response = $this->actingAs($manager)->postJson(route('case-issues.quick'), ['name' => 'Raced Issue']);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('errors.name.0'));
        $this->assertSame(0, CaseIssue::where('name', 'Raced Issue')->count(), 'garbage created by the losing caller rolls back');
    }
}
