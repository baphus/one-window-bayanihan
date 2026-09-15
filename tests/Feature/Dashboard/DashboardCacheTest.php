<?php

namespace Tests\Feature\Dashboard;

use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Client;
use App\Models\Referral;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_dashboard_summary_cached(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);

        $data = app(DashboardService::class)->getCaseManagerData($manager);

        $this->assertIsArray($data['recentActivity']);
        $this->assertTrue(Cache::has('dashboard:cm_recent_activity'));
    }

    public function test_dashboard_cache_hit_on_second_call(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $service = app(DashboardService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $service->getCaseManagerData($manager);
        $firstCount = count(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $service->getCaseManagerData($manager);
        $secondCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Same cached recent-activity payload, served without re-querying.
        $this->assertSame($first['recentActivity'], $second['recentActivity']);
        $this->assertSame(Cache::get('dashboard:cm_recent_activity'), $second['recentActivity']);
        $this->assertLessThan($firstCount, $secondCount);
    }

    public function test_referral_write_invalidates_dashboard_caches(): void
    {
        $agency = Agency::factory()->create();
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $agencyUser = User::factory()->create(['role' => 'AGENCY', 'agcy_id' => $agency->id]);
        $service = app(DashboardService::class);

        $service->getCaseManagerData($manager);
        $service->getAgencyData($agencyUser);
        $service->getAdminData();
        $this->assertTrue(Cache::has('dashboard:cm_recent_activity'));
        $this->assertTrue(Cache::has('dashboard:agency_recent_activity:'.$agency->id));
        $this->assertTrue(Cache::has('dashboard:admin_recent_logs'));

        $case = CaseFile::factory()->create([
            'client_id' => Client::factory()->create()->id,
        ]);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id]);

        $this->assertFalse(Cache::has('dashboard:cm_recent_activity'));
        $this->assertFalse(Cache::has('dashboard:agency_recent_activity:'.$agency->id));
        $this->assertFalse(Cache::has('dashboard:admin_recent_logs'));
    }

    public function test_case_write_invalidates_dashboard_caches(): void
    {
        $manager = User::factory()->create(['role' => 'CASE_MANAGER']);
        $service = app(DashboardService::class);

        $service->getCaseManagerData($manager);
        $service->getAdminData();
        $this->assertTrue(Cache::has('dashboard:cm_recent_activity'));
        $this->assertTrue(Cache::has('dashboard:admin_recent_logs'));

        CaseFile::factory()->create([
            'client_id' => Client::factory()->create()->id,
        ]);

        $this->assertFalse(Cache::has('dashboard:cm_recent_activity'));
        $this->assertFalse(Cache::has('dashboard:admin_recent_logs'));
    }
}
