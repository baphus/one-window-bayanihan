<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use App\Services\DefaultAgencyService;
use Database\Seeders\AgencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultAgencyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgencySeeder::class);
    }

    public function test_get_default_agency_returns_dmw(): void
    {
        $service = app(DefaultAgencyService::class);
        $default = $service->getDefaultAgency();

        $this->assertNotNull($default);
        $this->assertEquals('dmw', $default->slug);
    }

    public function test_assign_default_agency_sets_agcy_id(): void
    {
        $service = app(DefaultAgencyService::class);
        $user = User::factory()->create(['agcy_id' => null]);

        $result = $service->assignDefaultAgency($user);

        $this->assertNotNull($result->agcy_id);
        $dmw = Agency::where('slug', 'dmw')->first();
        $this->assertEquals($dmw->id, $result->agcy_id);
    }

    public function test_assign_default_agency_does_not_overwrite(): void
    {
        $service = app(DefaultAgencyService::class);
        $agency = Agency::where('slug', 'owwa')->first();
        $user = User::factory()->create(['agcy_id' => $agency->id]);

        $result = $service->assignDefaultAgency($user);

        $this->assertEquals($agency->id, $result->agcy_id);
    }
}
