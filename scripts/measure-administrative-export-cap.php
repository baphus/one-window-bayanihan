<?php

use App\Services\Export\DataExportService;
use Illuminate\Contracts\Console\Kernel;
use PhpOffice\PhpSpreadsheet\IOFactory;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cap = max(1, (int) config('reports.export_row_cap', 6000));
$rows = collect(range(1, $cap))->map(fn (int $i) => ['reference' => 'CASE-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT), 'created' => '2026-01-01', 'status' => 'OPEN', 'categories' => 'Employment assistance', 'referral_count' => 1]);
$referrals = collect(range(1, $cap))->map(fn (int $i) => ['reference' => 'Restricted reference '.sprintf('%08d', $i), 'case_reference' => 'CASE-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT), 'agency' => 'Agency', 'sent_date' => '', 'status' => 'PENDING', 'completion_date' => '']);
$start = hrtime(true);
$path = app(DataExportService::class)->generateMultiSheetFile([
    ['title' => 'Scope & Definitions', 'columnMap' => [['label' => 'Item', 'key' => 'item'], ['label' => 'Value', 'key' => 'value']], 'rows' => collect([['item' => 'Scope', 'value' => str_repeat('Current-state definitions. ', 8)]])],
    ['title' => 'Management Summary', 'columnMap' => [['label' => 'Metric', 'key' => 'metric'], ['label' => 'Label', 'key' => 'label'], ['label' => 'Count', 'key' => 'count', 'type' => 'int']], 'rows' => collect([['metric' => 'Case status', 'label' => 'OPEN', 'count' => $cap]])],
    ['title' => 'Cases', 'columnMap' => [['label' => 'Case reference (restricted)', 'key' => 'reference'], ['label' => 'Created date', 'key' => 'created'], ['label' => 'Status', 'key' => 'status', 'type' => 'status'], ['label' => 'Categories', 'key' => 'categories'], ['label' => 'Exported referral count', 'key' => 'referral_count', 'type' => 'int']], 'rows' => $rows],
    ['title' => 'Referrals', 'columnMap' => [['label' => 'Referral reference (restricted)', 'key' => 'reference'], ['label' => 'Case reference (restricted)', 'key' => 'case_reference'], ['label' => 'Agency', 'key' => 'agency'], ['label' => 'Sent date', 'key' => 'sent_date'], ['label' => 'Status', 'key' => 'status', 'type' => 'status'], ['label' => 'Completion date', 'key' => 'completion_date']], 'rows' => $referrals],
    ['title' => 'Client Statistics', 'columnMap' => [['label' => 'Distribution', 'key' => 'distribution'], ['label' => 'Value', 'key' => 'value'], ['label' => 'Count', 'key' => 'count']], 'rows' => collect([['distribution' => 'Sex', 'value' => 'Suppressed', 'count' => 'Suppressed']])],
], 'admin-cap-measure.xlsx');
$duration = (hrtime(true) - $start) / 1e9;
try {
    $spreadsheet = IOFactory::load($path);
    if ($spreadsheet->getSheetCount() !== 5 || $spreadsheet->getSheet(2)->getHighestDataRow() !== $cap + 1 || $spreadsheet->getSheet(3)->getHighestDataRow() !== $cap + 1) {
        throw new RuntimeException('Cap workbook validation failed.');
    }
    printf("cap=%d case_rows=%d referral_rows=%d duration_seconds=%.2f peak_memory_mb=%.1f bytes=%d exit_status=0 artifact_valid=yes\n", $cap, $cap, $cap, $duration, memory_get_peak_usage(true) / 1048576, filesize($path));
    $spreadsheet->disconnectWorksheets();
} finally {
    @unlink($path);
}
