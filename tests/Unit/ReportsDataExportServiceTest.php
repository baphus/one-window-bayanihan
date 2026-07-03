<?php

namespace Tests\Unit;

use App\Services\Export\DataExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsDataExportServiceTest extends TestCase
{
    #[Test]
    public function xlsx_writer_neutralizes_formula_like_strings(): void
    {
        $service = new DataExportService;
        $response = $service->generateSingleSheet('Formula Safety', [
            ['key' => 'value', 'label' => 'Value', 'type' => 'string'],
        ], collect([
            ['value' => '=IMPORTXML("https://example.com")'],
            ['value' => ' +SUM(1,2)'],
            ['value' => '@cmd'],
            ['value' => 'normal text'],
        ]), 'formula-safety.xlsx');

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $content);

        $sheet = IOFactory::load($file)->getActiveSheet();

        $this->assertSame('Value', $sheet->getCell('A1')->getValue());
        $this->assertSame("'=IMPORTXML(\"https://example.com\")", $sheet->getCell('A2')->getValue());
        $this->assertSame("' +SUM(1,2)", $sheet->getCell('A3')->getValue());
        $this->assertSame("'@cmd", $sheet->getCell('A4')->getValue());
        $this->assertSame('normal text', $sheet->getCell('A5')->getValue());

        @unlink($file);
    }
}
