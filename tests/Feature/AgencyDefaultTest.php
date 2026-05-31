<?php

namespace Tests\Feature;

use App\Models\Agency;
use Database\Seeders\AgencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgencySeeder::class);
    }

    public function test_dmw_is_the_default_agency(): void
    {
        $dmw = Agency::where('slug', 'dmw')->first();

        $this->assertNotNull($dmw);
        $this->assertTrue($dmw->is_default);
    }

    public function test_only_one_agency_is_default(): void
    {
        $defaultCount = Agency::where('is_default', true)->count();

        $this->assertEquals(1, $defaultCount);
    }
}
