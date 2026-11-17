<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\User;
use App\Services\Export\DataExportService;
use App\Services\Reports\AdministrativeExportService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdministrativeExportPrivacyTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'bayanihan_test') {
            throw new \RuntimeException('Administrative export tests require pgsql/bayanihan_test.');
        }
    }

    #[Test]
    public function counts_linked_clients_once_and_suppresses_small_demographics(): void
    {
        config(['reports.suppression_threshold' => 5]);
        $client = User::factory()->create();
        $cases = collect();
        for ($i = 0; $i < 6; $i++) {
            $cases->push(CaseFile::factory()->withIntake()->create([
                'ofw_user_id' => $client->id,
                'created_at' => '2036-01-0'.($i + 1).' 12:00:00',
            ]));
        }
        $this->assertSame(1, $cases->pluck('ofw_user_id')->unique()->count());
        $statistics = app(AdministrativeExportService::class)->clientStatistics($cases);
        $sexRows = $statistics->where('distribution', 'Sex')->values();
        $this->assertSame('Suppressed', $sexRows->firstWhere('value', 'Suppressed')['count']);
        $this->assertSame(0, $sexRows->firstWhere('value', 'FEMALE')['count']);
        $this->assertSame(1, $statistics->firstWhere('distribution', 'Linked clients')['count']);
        $this->assertStringNotContainsString('1990-06-15', json_encode($statistics));
        $this->assertSame('0-17', app(AdministrativeExportService::class)->clientStatistics(collect([CaseFile::factory()->withIntake()->make(['date_of_birth' => '2009-11-10'])]))->firstWhere('distribution', 'Age at intake')['value']);
    }

    #[Test]
    public function latest_linked_snapshot_controls_demographics_and_age_boundaries(): void
    {
        config(['reports.suppression_threshold' => 1]);
        $cases = collect();
        foreach ([17 => '0-17', 18 => '18-25', 25 => '18-25', 26 => '26-40', 40 => '26-40', 41 => '41-60', 60 => '41-60', 61 => '61+'] as $age => $band) {
            $client = User::factory()->create();
            $cases->push(CaseFile::factory()->withIntake()->create([
                'ofw_user_id' => $client->id, 'created_at' => '2036-01-01', 'sex' => 'MALE',
                'date_of_birth' => '1900-01-01', 'intake_date' => '2026-06-15',
            ]));
            $latest = CaseFile::factory()->withIntake()->create([
                'ofw_user_id' => $client->id, 'created_at' => '2036-02-01', 'sex' => 'FEMALE',
                'date_of_birth' => (2026 - $age).'-06-15', 'intake_date' => '2026-06-15',
                'address_province' => 'Cebu',
            ]);
            $this->assertNotSame($latest->getRawOriginal('date_of_birth'), $latest->date_of_birth->format('Y-m-d'));
            $cases->push($latest);
        }
        $cases->push(CaseFile::factory()->withIntake()->make(['ofw_user_id' => null]));
        $rows = app(AdministrativeExportService::class)->clientStatistics($cases);
        $this->assertSame(8, $rows->firstWhere('distribution', 'Linked clients')['count']);
        $this->assertSame(8, $rows->where('distribution', 'Sex')->firstWhere('value', 'FEMALE')['count']);
        $this->assertSame(0, $rows->where('distribution', 'Sex')->firstWhere('value', 'MALE')['count']);
        foreach (['0-17' => 1, '18-25' => 2, '26-40' => 2, '41-60' => 2, '61+' => 1] as $band => $count) {
            $this->assertSame($count, $rows->where('distribution', 'Age at intake')->firstWhere('value', $band)['count']);
        }
        $this->assertSame(8, $rows->where('distribution', 'Province')->firstWhere('value', 'Cebu')['count']);
    }

    #[Test]
    public function complementary_suppression_prevents_subtraction_while_preserving_zero(): void
    {
        config(['reports.suppression_threshold' => 5]);
        $cases = collect();
        for ($i = 0; $i < 6; $i++) {
            $cases->push(CaseFile::factory()->withIntake()->make([
                'ofw_user_id' => (string) Str::uuid(),
                'sex' => $i === 0 ? 'MALE' : 'FEMALE',
            ]));
        }
        $rows = app(AdministrativeExportService::class)->clientStatistics($cases)->where('distribution', 'Sex');
        $this->assertCount(2, $rows->where('count', 'Suppressed'));
        $this->assertSame(0, $rows->firstWhere('value', 'Unknown')['count']);
    }

    #[Test]
    public function threshold_boundary_and_unknown_values_remain_explicit(): void
    {
        config(['reports.suppression_threshold' => 5]);
        $cases = collect();
        foreach (range(1, 5) as $index) {
            $cases->push(CaseFile::factory()->make([
                'ofw_user_id' => (string) Str::uuid(),
                'sex' => null, 'date_of_birth' => null, 'intake_date' => null, 'address_province' => null,
            ]));
        }
        $rows = app(AdministrativeExportService::class)->clientStatistics($cases);
        $this->assertSame(5, $rows->where('distribution', 'Sex')->firstWhere('value', 'Unknown')['count']);
        $this->assertSame(5, $rows->where('distribution', 'Age at intake')->firstWhere('value', 'Unknown')['count']);
        $this->assertSame(5, $rows->where('distribution', 'Province')->firstWhere('value', 'Unknown')['count']);
        $small = app(AdministrativeExportService::class)->clientStatistics($cases->take(4));
        $this->assertSame('Suppressed', $small->where('distribution', 'Sex')->firstWhere('value', 'Suppressed')['count']);
    }

    #[Test]
    public function writer_creates_xlsx_with_formula_like_allowed_text(): void
    {
        $path = app(DataExportService::class)->generateMultiSheetFile([
            ['title' => 'Cases', 'columnMap' => [['label' => 'Reference', 'key' => 'reference']], 'rows' => collect([['reference' => '=1+1']])],
        ], 'privacy-test.xlsx');
        try {
            $this->assertTrue(is_file($path));
            $zip = new \ZipArchive;
            $this->assertSame(true, $zip->open($path));
            $this->assertStringContainsString("'=1+1", $zip->getFromName('xl/sharedStrings.xml'));
            $zip->close();
        } finally {
            @unlink($path);
        }
    }
}
