<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\Referral;
use App\Services\Export\DataExportService;
use App\Support\ReportCalendar;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TimezonePresentationTest extends TestCase
{
    public function test_referral_status_and_milestone_dates_use_operating_timezone_without_mutation(): void
    {
        config(['app.operating_timezone' => 'Asia/Manila']);
        $referral = new Referral;
        $referral->status = 'COMPLETED';
        $referral->created_at = '2026-11-16 18:00:00';
        $referral->updated_at = '2026-11-17 05:36:00';
        $referral->setRelation('milestones', new Collection);

        $this->assertSame('November 17, 2026 at 01:36 PM', $referral->latest_update['date']);
        $this->assertSame('05:36', $referral->updated_at->format('H:i'));

        $milestone = new Milestone;
        $milestone->title = 'Milestone recorded';
        $milestone->created_at = '2026-11-17 06:00:00';
        $referral->setRelation('milestones', new Collection([$milestone]));

        $this->assertSame('November 17, 2026 at 02:00 PM', $referral->latest_update['date']);
        $this->assertSame('06:00', $milestone->created_at->format('H:i'));
    }

    public function test_operating_day_uses_inclusive_start_and_exclusive_end_in_utc(): void
    {
        config(['app.operating_timezone' => 'Asia/Manila']);

        [$start, $end] = ReportCalendar::localDayBounds('2026-11-17');

        $this->assertSame('2026-11-16 16:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-17 16:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_spreadsheet_converts_timestamps_but_preserves_calendar_dates(): void
    {
        config(['app.operating_timezone' => 'Asia/Manila']);
        $response = (new DataExportService)->generateSingleSheet('Dates', [
            ['key' => 'created', 'label' => 'Created', 'type' => 'date'],
            ['key' => 'birthday', 'label' => 'Birthday', 'type' => 'date'],
        ], collect([['created' => '2026-11-16T19:36:00Z', 'birthday' => '1990-11-16']]), 'dates.xlsx');
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $file = tempnam(sys_get_temp_dir(), 'timezone');
        try {
            file_put_contents($file, $content);
            $sheet = IOFactory::load($file)->getActiveSheet();

            $this->assertSame('2026-11-17', $sheet->getCell('A2')->getValue());
            $this->assertSame('1990-11-16', $sheet->getCell('B2')->getValue());
        } finally {
            unlink($file);
        }
    }
}
