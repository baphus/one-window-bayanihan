<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The reports page and the referral index read other people's records: system
 * wide aggregates, and client addresses on every referral row. Both used to sit
 * in the all-roles group with no role middleware, so a filer account reached
 * them directly. The routes now name the three staff roles, and the report
 * service fails closed underneath as well so a caller that reaches it another
 * way still gets nothing.
 */
class ReportAndReferralRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HandleInertiaRequests::class);

        $this->service = app(ReportsService::class);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function guardedRouteProvider(): array
    {
        return [
            'reports index' => ['reports.index', 'GET'],
            'reports pdf export' => ['reports.export-pdf', 'GET'],
            'reports excel export' => ['reports.export-excel', 'GET'],
            'referral index' => ['referrals.index', 'GET'],
            'referral create form' => ['referrals.create', 'GET'],
        ];
    }

    #[Test]
    #[DataProvider('guardedRouteProvider')]
    public function ofw_is_forbidden_from_guarded_routes(string $routeName, string $method): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);

        $this->actingAs($ofw)
            ->{$method}(route($routeName))
            ->assertStatus(403);
    }

    #[Test]
    #[DataProvider('guardedRouteProvider')]
    public function the_three_staff_roles_keep_their_guarded_routes(string $routeName, string $method): void
    {
        foreach ([UserRole::CASE_MANAGER->value, UserRole::ADMIN->value, UserRole::AGENCY->value] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'agcy_id' => $role === UserRole::AGENCY->value ? Agency::factory()->create()->id : null,
            ]);

            $this->actingAs($user)
                ->{$method}(route($routeName))
                ->assertStatus(200);
        }
    }

    /**
     * An agency session with no agency has no scope to apply. All three report
     * entry points share one guard now, so this pins them to the same refusal
     * rather than assuming each action remembered its own copy.
     */
    #[Test]
    public function an_unlinked_agency_user_is_forbidden_from_every_report_entry_point(): void
    {
        $agencyUser = User::factory()->create([
            'role' => UserRole::AGENCY->value,
            'agcy_id' => null,
        ]);

        foreach (['reports.index', 'reports.export-pdf', 'reports.export-excel'] as $routeName) {
            $response = $this->actingAs($agencyUser)->get(route($routeName));

            $this->assertSame(
                403,
                $response->status(),
                "Route [{$routeName}] must refuse an agency user with no agency."
            );
        }
    }

    /**
     * Pin the guard to the route definition itself: the role list has to be on
     * the route, not only on a controller check that a later refactor can drop.
     */
    #[Test]
    public function guarded_routes_carry_the_staff_role_middleware(): void
    {
        foreach (array_keys(self::guardedRouteProvider()) as $case) {
            $routeName = self::guardedRouteProvider()[$case][0];
            $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

            $this->assertContains(
                'role:CASE_MANAGER,ADMIN,AGENCY',
                $middleware,
                "Route [{$routeName}] is missing the staff role guard."
            );
        }
    }

    /**
     * Defence in depth: even a caller that reaches the service directly with a
     * role outside the allowlist gets the empty payload, not the case manager
     * payload the match statement's default branch used to hand out.
     */
    #[Test]
    public function the_report_payload_fails_closed_for_a_role_outside_the_allowlist(): void
    {
        $ofw = User::factory()->create(['role' => UserRole::OFW->value]);
        $this->createOverdueReferral();

        $payload = $this->service->getAll(
            userId: $ofw->id,
            role: UserRole::OFW->value,
            agencyId: null,
            fromDate: '2026-01-01',
            toDate: '2026-12-31',
        );

        $this->assertSame(0, $payload['kpis']['totalReferrals']);
        $this->assertSame(0, $payload['kpis']['totalCases']);
        // overdueReferrals left the payload entirely — an out-of-allowlist role
        // gets no overdue data at all, zero-shaped or otherwise.
        $this->assertArrayNotHasKey('overdueReferrals', $payload);
        $this->assertSame(UserRole::OFW->value, $payload['role']);
    }

    #[Test]
    public function an_unknown_role_string_is_treated_as_out_of_scope(): void
    {
        $payload = $this->service->getAll(
            userId: (string) Str::uuid(),
            role: 'SUPERUSER',
            agencyId: null,
        );

        $this->assertSame(0, $payload['kpis']['totalReferrals']);
        $this->assertArrayNotHasKey('overdueReferrals', $payload);
    }

    /**
     * The overdue panel used to build its own unscoped query and ignored both
     * the caller's agency and the caller's role. The rows carry the client's
     * name and address, so that was a cross-agency PII read.
     */
    #[Test]
    public function overdue_referrals_are_scoped_to_the_callers_agency(): void
    {
        $agencyA = Agency::factory()->create(['name' => 'Agency A']);
        $agencyB = Agency::factory()->create(['name' => 'Agency B']);

        $agencyAReferral = $this->createOverdueReferral($agencyA->id);
        $agencyBReferral = $this->createOverdueReferral($agencyB->id);

        $scoped = $this->service->getOverdueReferrals(
            userId: null,
            role: UserRole::AGENCY->value,
            agencyId: $agencyA->id,
        );

        $this->assertSame(1, $scoped['count']);
        $this->assertSame(
            [$agencyAReferral->id],
            collect($scoped['referrals']->items())->pluck('id')->all()
        );

        $unscoped = $this->service->getOverdueReferrals(
            userId: null,
            role: UserRole::ADMIN->value,
            agencyId: null,
        );

        $this->assertSame(2, $unscoped['count']);
    }

    #[Test]
    public function overdue_referrals_are_empty_for_a_role_outside_the_allowlist(): void
    {
        $this->createOverdueReferral();

        $result = $this->service->getOverdueReferrals(
            userId: null,
            role: UserRole::OFW->value,
            agencyId: null,
        );

        $this->assertSame(0, $result['count']);
        $this->assertCount(0, $result['referrals']->items());
    }

    /**
     * A pending referral created more than the 14 day threshold ago, which is
     * what the overdue panel selects on.
     */
    private function createOverdueReferral(?string $agencyId = null): Referral
    {
        $case = CaseFile::factory()->create([
            'status' => 'OPEN',
            'created_at' => now()->subMonths(3),
        ]);

        $referral = Referral::factory()->create([
            'case_id' => $case->id,
            'agcy_id' => $agencyId ?? Agency::factory()->create()->id,
            'status' => 'PENDING',
            'created_at' => now()->subMonths(3),
        ]);

        return $referral->fresh();
    }
}
