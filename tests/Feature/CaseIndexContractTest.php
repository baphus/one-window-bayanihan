<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Case index filter/sort/stats contract: what the Case/Index screen sends,
 * what the index accepts, and what the stat cards read.
 */
class CaseIndexContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);

        // getCaseStats memoises for two minutes under 'stats:cases'; without a
        // flush a previous test's counts would leak into this one.
        Cache::flush();
    }

    /**
     * Three live cases whose client type, client last name and author each rank
     * differently, so a sort that silently does nothing cannot pass:
     *
     *   | case    | client        | type       | author         |
     *   |---------|---------------|------------|----------------|
     *   | C-0001  | Ana Mike      | OFW        | Zeta Zulu      |
     *   | C-0002  | Bob Yanez     | NEXT_OF_KIN | Mia Baker      |
     *   | C-0003  | Rita Zulu     | OFW        | Alpha Adams    |
     *
     * Plus an ARCHIVED case that the index hides and the stats tile excludes.
     *
     * @return array{admin: User}
     */
    private function seedCases(): array
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $zeta = User::factory()->create(['role' => UserRole::CASE_MANAGER->value, 'name' => 'Zeta Zulu']);
        $mia = User::factory()->create(['role' => UserRole::CASE_MANAGER->value, 'name' => 'Mia Baker']);
        $alpha = User::factory()->create(['role' => UserRole::CASE_MANAGER->value, 'name' => 'Alpha Adams']);

        $row = static fn (string $number, string $first, string $last, string $type, User $author, string $createdAt, string $status) => [
            'case_number' => $number,
            'tracker_number' => 'TRK-'.$number,
            'client_id' => Client::factory()->create([
                'first_name' => $first,
                'last_name' => $last,
                'type' => $type,
            ])->id,
            'user_id' => $author->id,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        $c1 = CaseFile::factory()->create($row('C-0001', 'Ana', 'Mike', 'OFW', $zeta, '2026-03-01 09:00:00', 'OPEN'));
        $c2 = CaseFile::factory()->create($row('C-0002', 'Bob', 'Yanez', 'NEXT_OF_KIN', $mia, '2026-02-01 09:00:00', 'CLOSED'));
        $c3 = CaseFile::factory()->create($row('C-0003', 'Rita', 'Zulu', 'OFW', $alpha, '2026-01-01 09:00:00', 'OPEN'));

        // Hidden from the index and excluded from the client-type tile.
        CaseFile::factory()->create($row('C-0004', 'Hidden', 'Archived', 'OFW', $alpha, '2026-04-01 09:00:00', 'ARCHIVED'));

        return ['admin' => $admin, 'c1' => $c1, 'c2' => $c2, 'c3' => $c3];
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }

    // =========================================================================
    // H-f: the client_type filter
    // =========================================================================

    public function test_client_type_filter_returns_only_cases_of_that_client_type(): void
    {
        ['admin' => $admin, 'c1' => $c1, 'c2' => $c2, 'c3' => $c3] = $this->seedCases();

        $ofw = $this->actingAs($admin)->get(route('cases.index', ['client_type' => 'OFW']));
        $ofw->assertOk();

        $ids = collect($this->props($ofw)['cases']['data'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$c1->id, $c3->id], $ids);

        $nok = $this->actingAs($admin)->get(route('cases.index', ['client_type' => 'NEXT_OF_KIN']));
        $nok->assertOk();

        $this->assertSame([$c2->id], collect($this->props($nok)['cases']['data'])->pluck('id')->all());
    }

    public function test_client_type_filter_round_trips_in_the_echoed_filters(): void
    {
        ['admin' => $admin] = $this->seedCases();

        // The chip, the select and the export dialog all read the echoed prop,
        // so a filter that is applied but not echoed silently resets on reload.
        $this->actingAs($admin)
            ->get(route('cases.index', ['client_type' => 'OFW', 'status' => 'OPEN']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.client_type', ['OFW'])
                ->where('filters.status', ['OPEN'])
            );
    }

    public function test_client_type_filter_rejects_an_unknown_value(): void
    {
        ['admin' => $admin] = $this->seedCases();

        $this->actingAs($admin)
            ->get(route('cases.index', ['client_type' => 'PERSONAL']))
            ->assertStatus(302)
            ->assertSessionHasErrors('client_type');
    }

    // =========================================================================
    // H-g: the client-type stat card
    // =========================================================================

    public function test_case_stats_report_ofw_and_nok_case_counts(): void
    {
        ['admin' => $admin] = $this->seedCases();

        $this->actingAs($admin)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.ofw_cases', 2)
                ->where('stats.nok_cases', 1)
                // Same population as the client-type tile: outside DRAFT/ARCHIVED.
                ->where('stats.total_cases', 3)
            );
    }

    // =========================================================================
    // H-h: sorting the columns the UI offers as sortable
    // =========================================================================

    public function test_client_type_sort_orders_by_the_clients_type(): void
    {
        ['admin' => $admin, 'c2' => $c2] = $this->seedCases();

        $this->assertSortsFirst($admin, 'client_type', 'asc', $c2->id);
    }

    public function test_client_name_sort_orders_by_the_clients_name(): void
    {
        ['admin' => $admin, 'c1' => $c1] = $this->seedCases();

        $this->assertSortsFirst($admin, 'client_name', 'asc', $c1->id);
    }

    public function test_author_sort_orders_by_the_filers_name(): void
    {
        ['admin' => $admin, 'c3' => $c3] = $this->seedCases();

        $this->assertSortsFirst($admin, 'author', 'asc', $c3->id);
    }

    public function test_age_is_not_an_accepted_sort_key(): void
    {
        // clients.date_of_birth is encrypted, so age is not orderable in SQL.
        // The column is no longer marked sortable in Case/Index.jsx; the
        // whitelist has to agree, so the key is rejected like any unknown one.
        ['admin' => $admin] = $this->seedCases();

        $this->assertArrayNotHasKey('age', CaseService::SORT_KEYS);

        $this->actingAs($admin)
            ->get(route('cases.index', ['sort' => 'age']))
            ->assertStatus(302)
            ->assertSessionHasErrors('sort');
    }

    public function test_age_column_is_not_marked_sortable_in_the_index_page(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Case/Index.jsx'));

        $this->assertMatchesRegularExpression(
            "/case 'age':.*?sortable: false/s",
            $source,
            'The age column still advertises sortable, so the header keeps sending sort=age.'
        );
    }

    // =========================================================================
    // Regression: the sort keys that already worked
    // =========================================================================

    public function test_existing_sort_keys_still_order_the_list(): void
    {
        ['admin' => $admin, 'c1' => $c1, 'c3' => $c3] = $this->seedCases();

        $this->assertSortsFirst($admin, 'case_number', 'asc', $c1->id);
        // TRK-C-0003 > TRK-C-0002 > TRK-C-0001 as strings, so desc puts C-0003 first.
        $this->assertSortsFirst($admin, 'tracker_number', 'desc', $c3->id);

        // created_at: C-0003 (Jan) < C-0002 (Feb) < C-0001 (Mar)
        $this->assertSortsFirst($admin, 'created_at', 'desc', $c1->id);
        $this->assertSortsFirst($admin, 'created_at', 'asc', $c3->id);
    }

    public function test_existing_sort_keys_are_accepted_by_the_whitelist(): void
    {
        foreach (['case_number', 'tracker_number', 'status', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, CaseService::SORT_KEYS);
        }
    }

    /**
     * The fixture deliberately makes every sort key resolve to a different
     * first row, so this fails loudly if a key is whitelisted but not applied.
     */
    private function assertSortsFirst(User $admin, string $sort, string $direction, string $expectedFirstId): void
    {
        $response = $this->actingAs($admin)->get(route('cases.index', [
            'sort' => $sort,
            'direction' => $direction,
        ]));

        $response->assertOk();

        $rows = $this->props($response)['cases']['data'];

        $this->assertNotEmpty($rows, "sort={$sort} returned no rows");
        $this->assertSame(
            $expectedFirstId,
            $rows[0]['id'],
            "sort={$sort}&direction={$direction} did not order the list."
        );
    }
}
