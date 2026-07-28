<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\CaseService;
use App\Services\Export\DataExportQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A self-filed intake creates its Client row at submission time, before any Case
 * Manager has looked at it. Until that intake is accepted the person is an
 * unverified claim, not a client of the programme, and must not surface anywhere
 * that presents established clients — most visibly the "select existing client"
 * picker on the case-creation form, where picking one would attach a real case to
 * unreviewed data.
 *
 * The intake queue itself is the one place these records belong, and it queries
 * cases directly, so it is unaffected.
 *
 * "Unaccepted" means source=self_filed and status=DRAFT, whether or not the case
 * has been soft-deleted. Acceptance moves the case to OPEN and makes the client
 * visible. Rejection soft-deletes the CASE but leaves the CLIENT row, so a rule
 * that only looked at live cases would read a rejected filer as having no pending
 * intake and publish them — rejection, the strongest evidence the data is bogus,
 * would have been the thing that revealed it. Hence withTrashed().
 */
class PendingIntakeClientVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function pendingIntakeClient(array $attributes = []): Client
    {
        $client = Client::factory()->create($attributes + ['is_deleted' => false]);

        CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => null,
            'status' => 'DRAFT',
            'source' => CaseFile::SOURCE_SELF_FILED,
            'intake_reviewed_by' => null,
        ]);

        return $client;
    }

    private function caseManager(): User
    {
        return User::factory()->create(['role' => 'CASE_MANAGER']);
    }

    #[Test]
    public function test_client_picker_hides_a_client_awaiting_intake_review(): void
    {
        $this->pendingIntakeClient(['first_name' => 'Pending', 'last_name' => 'Filer']);
        $established = Client::factory()->create(['first_name' => 'Established', 'is_deleted' => false]);
        CaseFile::factory()->create(['client_id' => $established->id, 'status' => 'OPEN']);

        $response = $this->actingAs($this->caseManager())->getJson('/api/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.first_name', 'Established');
    }

    #[Test]
    public function test_client_picker_search_cannot_reach_a_client_awaiting_intake_review(): void
    {
        $this->pendingIntakeClient(['first_name' => 'Pending', 'last_name' => 'Filer']);

        $response = $this->actingAs($this->caseManager())->getJson('/api/clients?q=Pending');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function test_client_detail_endpoint_hides_a_client_awaiting_intake_review(): void
    {
        // Guessing or reusing the UUID must not expose the filer's PII either.
        $client = $this->pendingIntakeClient();

        $response = $this->actingAs($this->caseManager())->getJson("/api/clients/{$client->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function test_clients_index_hides_a_client_awaiting_intake_review(): void
    {
        $this->pendingIntakeClient(['first_name' => 'Pending']);
        $established = Client::factory()->create(['first_name' => 'Established', 'is_deleted' => false]);
        CaseFile::factory()->create(['client_id' => $established->id, 'status' => 'OPEN']);

        $response = $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->get(route('clients.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Client/Index')
            ->has('clients.data', 1)
        );
    }

    #[Test]
    public function test_a_returning_client_with_a_reviewed_case_stays_visible_while_a_new_intake_waits(): void
    {
        // Someone the programme already knows files again. The new intake is
        // pending, but the client themselves is established and must remain
        // selectable — hiding them would break case creation for repeat clients.
        $client = Client::factory()->create(['first_name' => 'Returning', 'is_deleted' => false]);
        CaseFile::factory()->create([
            'client_id' => $client->id,
            'status' => 'CLOSED',
        ]);
        CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => null,
            'status' => 'DRAFT',
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        $response = $this->actingAs($this->caseManager())->getJson('/api/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.first_name', 'Returning');
    }

    #[Test]
    public function test_a_client_with_no_cases_stays_visible(): void
    {
        // Pre-existing behaviour: a client whose case was deleted, or who was
        // captured ahead of a case, still belongs in the picker.
        Client::factory()->create(['first_name' => 'Caseless', 'is_deleted' => false]);

        $response = $this->actingAs($this->caseManager())->getJson('/api/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.first_name', 'Caseless');
    }

    #[Test]
    public function test_a_client_becomes_visible_once_the_intake_is_accepted(): void
    {
        $client = $this->pendingIntakeClient(['first_name' => 'Accepted']);
        $reviewer = $this->caseManager();

        $client->caseFiles()->update([
            'status' => 'OPEN',
            'intake_reviewed_by' => $reviewer->id,
        ]);

        $response = $this->actingAs($reviewer)->getJson('/api/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.first_name', 'Accepted');
    }

    #[Test]
    public function test_case_create_page_does_not_prefill_a_client_awaiting_intake_review(): void
    {
        $client = $this->pendingIntakeClient(['first_name' => 'Pending']);

        $response = $this->actingAs($this->caseManager())
            ->get(route('cases.create', ['client_id' => $client->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('client', null));
    }

    #[Test]
    public function test_case_create_page_still_prefills_an_established_client(): void
    {
        $client = Client::factory()->create(['first_name' => 'Established', 'is_deleted' => false]);
        CaseFile::factory()->create(['client_id' => $client->id, 'status' => 'CLOSED']);

        $response = $this->actingAs($this->caseManager())
            ->get(route('cases.create', ['client_id' => $client->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('client.id', $client->id));
    }

    #[Test]
    public function test_a_case_cannot_be_created_against_a_client_awaiting_intake_review(): void
    {
        // Closes the write path the hidden picker protects: posting the id
        // directly must be refused too.
        $client = $this->pendingIntakeClient();

        $response = $this->actingAs($this->caseManager())->post(route('cases.store'), [
            'selected_client_id' => $client->id,
            'client_type' => 'OFW',
            'is_draft' => true,
        ]);

        $response->assertSessionHasErrors('selected_client_id');
    }

    #[Test]
    public function test_client_exports_exclude_clients_awaiting_intake_review(): void
    {
        // getClientsExport is hand-written SQL, so the Eloquent scope does not
        // reach it — it needs its own guard and its own test.
        $this->pendingIntakeClient(['first_name' => 'Pending', 'last_name' => 'Filer']);
        $established = Client::factory()->create([
            'first_name' => 'Established',
            'last_name' => 'Client',
            'is_deleted' => false,
        ]);
        CaseFile::factory()->create(['client_id' => $established->id, 'status' => 'OPEN']);

        // The export strips the raw name columns and emits "Last, First".
        $names = collect((new DataExportQueries)
            ->getClientsExport(User::factory()->create(['role' => 'ADMIN'])))
            ->pluck('full_name')
            ->all();

        $this->assertContains('Client, Established', $names);
        $this->assertNotContains('Filer, Pending', $names);
    }

    #[Test]
    public function test_client_exports_still_exclude_soft_deleted_clients(): void
    {
        // The pending-intake guard is an OR of two conditions; ungrouped it would
        // have overridden the is_deleted filter next to it.
        $deleted = Client::factory()->create([
            'first_name' => 'Deleted',
            'last_name' => 'Client',
            'is_deleted' => true,
        ]);
        CaseFile::factory()->create(['client_id' => $deleted->id, 'status' => 'OPEN']);

        $names = collect((new DataExportQueries)
            ->getClientsExport(User::factory()->create(['role' => 'ADMIN'])))
            ->pluck('full_name')
            ->all();

        $this->assertNotContains('Client, Deleted', $names);
    }

    #[Test]
    public function test_rejecting_an_intake_does_not_reveal_the_client(): void
    {
        // The regression this guards: rejectIntake() soft-deletes the case only.
        // A live-cases-only rule then saw "no pending intake" and exposed the
        // filer through the picker, the detail endpoint and the export — so
        // rejecting a bogus submission was what published it.
        $client = $this->pendingIntakeClient(['first_name' => 'Rejected', 'last_name' => 'Filer']);
        $reviewer = $this->caseManager();
        $case = $client->caseFiles()->firstOrFail();

        app(CaseService::class)->rejectIntake($case->id, 'Not an OFW concern.', $reviewer->id);

        $this->assertTrue($client->fresh()->hasOnlyUnacceptedIntake());

        $this->actingAs($reviewer)->getJson('/api/clients')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($reviewer)->getJson("/api/clients/{$client->id}")->assertNotFound();
        $this->actingAs($reviewer)->get(route('clients.show', $client->id))->assertNotFound();

        $names = collect((new DataExportQueries)->getClientsExport($reviewer))->pluck('full_name')->all();
        $this->assertNotContains('Filer, Rejected', $names);
    }

    #[Test]
    public function test_a_rejected_intake_does_not_hide_an_otherwise_established_client(): void
    {
        // Rejecting one submission must not erase a client the programme already
        // knows through a real case.
        $client = Client::factory()->create(['first_name' => 'Established', 'is_deleted' => false]);
        CaseFile::factory()->create(['client_id' => $client->id, 'status' => 'OPEN']);
        $intake = CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => null,
            'status' => 'DRAFT',
            'source' => CaseFile::SOURCE_SELF_FILED,
        ]);

        $reviewer = $this->caseManager();
        app(CaseService::class)->rejectIntake($intake->id, 'Duplicate of the open case.', $reviewer->id);

        $this->actingAs($reviewer)->getJson('/api/clients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Established');
    }

    #[Test]
    public function test_avatar_routes_refuse_a_client_hidden_from_the_directory(): void
    {
        // A record the profile refuses to show must not be writable either.
        $client = $this->pendingIntakeClient();

        $this->actingAs($this->caseManager())
            ->delete(route('clients.avatar.destroy', $client->id))
            ->assertNotFound();
    }

    #[Test]
    public function test_client_directory_total_matches_the_rows_it_lists(): void
    {
        // The listing was scoped but the "Total Clients" tile above it was not,
        // so the directory showed one row and claimed two.
        $this->pendingIntakeClient(['first_name' => 'Pending']);
        $established = Client::factory()->create(['first_name' => 'Established', 'is_deleted' => false]);
        CaseFile::factory()->create(['client_id' => $established->id, 'status' => 'OPEN']);

        // Each role gets a fresh user, so the per-user client_stats cache key is
        // distinct and cannot serve a value cached by the other.
        foreach (['ADMIN', 'CASE_MANAGER'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('clients.index'))
                ->assertStatus(200)
                ->assertInertia(fn ($page) => $page
                    ->has('clients.data', 1)
                    ->where('stats.total_clients', 1)
                );
        }
    }

    #[Test]
    public function test_a_staff_drafted_case_does_not_hide_its_client(): void
    {
        // Internal drafts are a Case Manager's own work in progress. They are not
        // unreviewed public submissions, so they must not hide the client.
        $client = Client::factory()->create(['first_name' => 'InternalDraft', 'is_deleted' => false]);
        CaseFile::factory()->create([
            'client_id' => $client->id,
            'status' => 'DRAFT',
            'source' => CaseFile::SOURCE_INTERNAL,
        ]);

        $response = $this->actingAs($this->caseManager())->getJson('/api/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.first_name', 'InternalDraft');
    }
}
