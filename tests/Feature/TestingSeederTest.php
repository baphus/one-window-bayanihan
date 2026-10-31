<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Services\CaseNumberGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_cases_share_the_counter_with_existing_and_future_cases(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 04:00:00', 'UTC'));
        $generator = app(CaseNumberGenerator::class);
        $existing = CaseFile::factory()->create([
            'case_number' => $generator->nextCaseNumber(),
        ]);

        $this->seed(TestingSeeder::class);

        $this->assertDatabaseHas('cases', ['id' => $existing->id, 'case_number' => 'OWB-202610-00001']);
        $this->assertDatabaseCount('cases', 851);
        $this->assertSame('OWB-202610-00852', $generator->nextCaseNumber());

        $clientCount = DB::table('clients')->count();
        $this->seed(TestingSeeder::class);

        $this->assertDatabaseCount('cases', 851);
        $this->assertDatabaseCount('clients', $clientCount);
        $this->assertSame('OWB-202610-00853', $generator->nextCaseNumber());
    }
}
