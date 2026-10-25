<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresSession;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Frontend/backend contract for two pages.
 *
 * Reports/Index: six producer keys the page never reads (the chart sections
 * that used them were removed from the UI) must no longer appear in the
 * payload — eager or deferred — while the keys the page does read stay intact.
 *
 * Client/Index: the directory sends sort keys `name` and `age`; the
 * controller's allowlist maps them onto real columns instead of silently
 * falling back to `created_at`.
 */
class ReportsAndClientContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Producer keys with no consumer left in resources/js/Pages/Reports/**.
     *
     * @var list<string>
     */
    private const REMOVED_REPORT_PROPS = [
        'referenceData',
        'overdueReferrals',
        'agencyWorkload',
        'referralAging',
        'reopenedStats',
        'caseEventActorDistribution',
    ];

    public function test_reports_index_hides_the_six_dead_keys_for_every_role(): void
    {
        foreach ([
            User::factory()->create(['role' => UserRole::CASE_MANAGER->value]),
            User::factory()->create(['role' => UserRole::ADMIN->value]),
        ] as $user) {
            $page = $this->inertiaPage($user);

            foreach (self::REMOVED_REPORT_PROPS as $key) {
                $this->assertArrayNotHasKey($key, $page['props'], "{$key} must not be an eager prop");
                $this->assertNotContains($key, $this->deferredPropNames($page), "{$key} must not be deferred either");
            }

            // The page still renders with the props it does read intact.
            $this->assertArrayHasKey('kpis', $page['props']);
            $this->assertArrayHasKey('referralStatusDistribution', $page['props']);
            $this->assertArrayHasKey('agencyScorecard', $page['props']);
            $deferred = $this->deferredPropNames($page);
            $this->assertContains('closedCasesOverTime', $deferred);
            $this->assertContains('agencyFirstResponse', $deferred);
        }
    }

    public function test_reports_page_still_resolves_its_remaining_props(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $version = app(HandleInertiaRequests::class)->version(Request::create(route('reports.index'), 'GET'));

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version ?? '',
                'X-Inertia-Partial-Component' => 'Reports/Index',
                'X-Inertia-Partial-Data' => 'referralStatusDistribution,agencyScorecard',
            ])->get(route('reports.index'));

        $response->assertOk();
        $props = $response->json('props');
        $this->assertArrayHasKey('referralStatusDistribution', $props);
        $this->assertArrayHasKey('agencyScorecard', $props);
    }

    /**
     * Directory rows where name order, age order and created_at order are all
     * pairwise different, so a wrong fallback is always visible:
     *
     *   name asc      Abel(Zeke) -> Chan(Ann) -> Dela(Pat)
     *   created desc  Pat(Dela)  -> Ann(Chan) -> Zeke(Abel)      (differs)
     *   age asc                          = DOB desc  -> 2010 -> 2000 -> 1990
     *   age desc                          = DOB asc   -> 1990 -> 2000 -> 2010
     *
     * @return array{0: Client, 1: Client, 2: Client}
     */
    private function seedSortRows(): array
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);

        $linkAccount = fn () => User::factory()->create(['role' => UserRole::OFW->value]);
        $withCase = fn (Client $client) => CaseFile::factory()->create([
            'client_id' => $client->id,
            'user_id' => $manager->id,
            'status' => 'OPEN',
        ]);

        $nameAscFirst = Client::factory()->create([
            'user_id' => $linkAccount()->id,
            'first_name' => 'Zeke',
            'last_name' => 'Abel',
            'sex' => 'FEMALE',
            'date_of_birth' => '2000-01-15',
            'created_at' => now()->subDays(30),
        ]);
        $withCase($nameAscFirst);

        $youngest = Client::factory()->create([
            'user_id' => $linkAccount()->id,
            'first_name' => 'Ann',
            'last_name' => 'Chan',
            'sex' => 'MALE',
            'date_of_birth' => '2010-09-30',
            'created_at' => now()->subDays(20),
        ]);
        $withCase($youngest);

        $oldest = Client::factory()->create([
            'user_id' => $linkAccount()->id,
            'first_name' => 'Pat',
            'last_name' => 'Dela',
            'sex' => 'MALE',
            'date_of_birth' => '1990-06-20',
            'created_at' => now()->subDays(10),
        ]);
        $withCase($oldest);

        return [$nameAscFirst, $youngest, $oldest];
    }

    /**
     * Directory rows in the order the page sent them.
     *
     * @return list<array<string, mixed>>
     */
    private function directoryRows(array $params = []): array
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->withoutMiddleware(HandleInertiaRequests::class);
        $this->withoutMiddleware(SetPostgresSession::class);

        $response = $this->actingAs($user)->get(route('clients.index', $params));

        $response->assertOk();

        return $response->viewData('page')['props']['clients']['data'];
    }

    public function test_client_directory_sort_name_uses_last_then_first(): void
    {
        [$abel, $chan, $dela] = $this->seedSortRows();

        $rows = $this->directoryRows(['sort' => 'name', 'direction' => 'asc']);

        $this->assertSame(
            [$abel->id, $chan->id, $dela->id],
            array_column($rows, 'id'),
            'name asc must order by last_name (the directory accessor renders "Last, First")'
        );

        $rows = $this->directoryRows(['sort' => 'name', 'direction' => 'desc']);

        $this->assertSame(
            array_reverse([$abel->id, $chan->id, $dela->id]),
            array_column($rows, 'id')
        );
    }

    public function test_client_directory_sort_age_means_what_the_arrow_says(): void
    {
        [$middle, $youngest, $oldest] = $this->seedSortRows();

        // UnifiedTable's first click sends asc: "Age ascending" = youngest first.
        $rows = $this->directoryRows(['sort' => 'age', 'direction' => 'asc']);

        $this->assertSame(
            [$youngest->id, $middle->id, $oldest->id],
            array_column($rows, 'id'),
            'age asc maps to date_of_birth desc (youngest first)'
        );

        $rows = $this->directoryRows(['sort' => 'age', 'direction' => 'desc']);

        $this->assertSame(
            [$oldest->id, $middle->id, $youngest->id],
            array_column($rows, 'id'),
            'age desc maps to date_of_birth asc (oldest first)'
        );
    }

    public function test_client_directory_sex_and_created_at_sorts_still_work(): void
    {
        [$abel, $chan, $dela] = $this->seedSortRows();

        // sex asc: FEMALE rows first (only Abel carries one).
        $rows = $this->directoryRows(['sort' => 'sex', 'direction' => 'asc']);

        $this->assertSame($abel->id, $rows[0]['id']);

        $rows = $this->directoryRows(['sort' => 'created_at', 'direction' => 'desc']);

        $this->assertSame([$dela->id, $chan->id, $abel->id], array_column($rows, 'id'));

        $rows = $this->directoryRows(['sort' => 'created_at', 'direction' => 'asc']);

        $this->assertSame([$abel->id, $chan->id, $dela->id], array_column($rows, 'id'));
    }

    public function test_client_directory_falls_back_silently_for_unknown_sort_keys(): void
    {
        [$abel, $chan, $dela] = $this->seedSortRows();

        // Default and bogus keys fall back to created_at desc, not 500s.
        foreach ([[], ['sort' => 'tracker_number'], ['sort' => ['name']]] as $params) {
            $rows = $this->directoryRows($params);

            $this->assertSame([$dela->id, $chan->id, $abel->id], array_column($rows, 'id'));
        }
    }

    /**
     * Fetch the Reports/Index page as Inertia would, with deferred prop
     * registrations available for inspection.
     *
     * @return array{component: string, props: array<string, mixed>}
     */
    private function inertiaPage(User $user): array
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create(route('reports.index'), 'GET'));

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version ?? '',
            ])->get(route('reports.index'));

        $response->assertOk();
        $response->assertHeader('X-Inertia', 'true');

        return $response->json();
    }

    /**
     * Deferred prop names from every defer group in the page payload
     * (Inertia serializes the page key as camelCase `deferredProps`,
     * grouped by defer group).
     *
     * @return list<string>
     */
    private function deferredPropNames(array $page): array
    {
        $names = [];

        foreach ((array) ($page['deferredProps'] ?? []) as $groupProps) {
            if (is_array($groupProps)) {
                array_push($names, ...$groupProps);

                continue;
            }

            $names[] = (string) $groupProps;
        }

        return $names;
    }
}
