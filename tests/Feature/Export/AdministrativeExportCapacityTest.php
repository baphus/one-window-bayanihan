<?php

namespace Tests\Feature\Export;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdministrativeExportCapacityTest extends TestCase
{
    use DatabaseTruncation;

    #[Test]
    public function full_request_at_configured_cap_stays_within_runtime_budget(): void
    {
        if (getenv('RUN_ADMIN_EXPORT_CAPACITY') !== '1') {
            $this->markTestSkipped('Opt-in capacity measurement; set RUN_ADMIN_EXPORT_CAPACITY=1.');
        }
        $cap = (int) config('reports.export_row_cap');
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $client = User::factory()->create(['role' => UserRole::OFW->value]);
        $agency = Agency::factory()->create();
        $caseTemplate = CaseFile::factory()->withIntake()->make([
            'user_id' => $admin->id, 'ofw_user_id' => $client->id,
            'created_at' => '2036-06-01 10:00:00', 'updated_at' => '2036-06-01 10:00:00',
        ])->getAttributes();
        $referralTemplate = Referral::factory()->make([
            'case_id' => null, 'agcy_id' => $agency->id,
            'created_at' => '2036-06-01 10:00:00', 'updated_at' => '2036-06-01 10:00:00',
        ])->getAttributes();
        foreach (array_chunk(range(1, $cap), 200) as $chunk) {
            $cases = $referrals = [];
            foreach ($chunk as $index) {
                $id = (string) Str::uuid();
                $cases[] = array_replace($caseTemplate, ['id' => $id, 'case_number' => 'CAP-'.$index, 'tracker_number' => 'CAP-TRACK-'.$index]);
                $referrals[] = array_replace($referralTemplate, ['id' => (string) Str::uuid(), 'case_id' => $id]);
            }
            DB::table('cases')->insert($cases);
            DB::table('referrals')->insert($referrals);
        }
        unset($cases, $referrals, $caseTemplate, $referralTemplate);
        $this->actingAsWithMfa($admin);
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $start = hrtime(true);
        $response = $this->getJson(route('admin.exports.administrative', ['from' => '2036-01-01', 'to' => '2036-12-31']));
        $response->assertOk();
        $duration = (hrtime(true) - $start) / 1e9;
        $peak = memory_get_peak_usage(true) / 1048576;
        $content = $response->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'admin-cap-check-');
        try {
            file_put_contents($path, $content);
            $book = IOFactory::load($path);
            $this->assertSame(5, $book->getSheetCount());
            $this->assertSame($cap + 1, $book->getSheetByName('Cases')->getHighestDataRow());
            $this->assertSame($cap + 1, $book->getSheetByName('Referrals')->getHighestDataRow());
            $book->disconnectWorksheets();
            $this->assertLessThan(60, $duration);
            $this->assertLessThan(224, $peak);
            fwrite(STDOUT, sprintf("\nFULL_REQUEST cases=%d referrals=%d seconds=%.2f peak_mb=%.1f bytes=%d valid=yes\n", $cap, $cap, $duration, $peak, strlen($content)));
        } finally {
            @unlink($path);
        }
    }
}
