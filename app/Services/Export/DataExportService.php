<?php

namespace App\Services\Export;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportService
{
    // ARGB color constants (format: AARRGGBB)
    private const COLOR_HEADER_BG = 'FF0B5A8C'; // DMW blue

    private const COLOR_HEADER_FONT = 'FFFFFFFF'; // white

    private const COLOR_ROW_EVEN = 'FFF8FAFC'; // light gray

    private const COLOR_ROW_ODD = 'FFFFFFFF'; // white

    /** Rows sampled to size columns; autosizing every row is what made exports time out. */
    private const WIDTH_SAMPLE_ROWS = 200;

    private const MIN_COLUMN_WIDTH = 10;

    private const MAX_COLUMN_WIDTH = 45;

    /** A chart over more categories than this is unreadable, so it is skipped. */
    private const MAX_CHART_ROWS = 60;

    private const STATUS_COLORS = [
        'COMPLETED' => 'FFD1FAE5', // green
        'CLOSED' => 'FFD1FAE5', // green
        'PENDING' => 'FFFEF3C7', // amber
        'PROCESSING' => 'FFDBEAFE', // blue
        'FOR_COMPLIANCE' => 'FFFFEDD5', // orange
        'REJECTED' => 'FFFEE2E2', // red
    ];

    /**
     * Generate a single-sheet XLSX streamed to the browser.
     *
     * @param  string  $title  Sheet tab name
     * @param  array  $columnMap  Array of ['label' => string, 'key' => string, 'type' => string?]
     *                            Supported types: 'string' (default), 'uuid', 'date', 'status'
     * @param  Collection  $rows  Eloquent collection or collection of arrays
     * @param  string  $filename  Download filename (e.g. "cases-export.xlsx")
     */
    public function generateSingleSheet(
        string $title,
        array $columnMap,
        Collection $rows,
        string $filename
    ): StreamedResponse {
        try {
            $spreadsheet = $this->createSpreadsheet($filename);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($this->sanitizeSheetTitle($title));

            $this->populateSheet($sheet, $columnMap, $rows);

            return $this->streamResponse($spreadsheet, $filename);
        } catch (\Throwable $e) {
            Log::error('DataExportService::generateSingleSheet failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse();
        }
    }

    /**
     * Generate a multi-sheet XLSX streamed to the browser.
     *
     * @param  array  $sheets  Array of sheet definitions:
     *                         ['title' => string, 'columnMap' => array, 'rows' => Collection]
     * @param  string  $filename  Download filename
     */
    public function generateMultiSheet(
        array $sheets,
        string $filename
    ): StreamedResponse {
        try {
            $spreadsheet = $this->createSpreadsheet($filename);

            foreach ($sheets as $index => $sheetDef) {
                $sheetTitle = $sheetDef['title'] ?? ('Sheet '.($index + 1));
                $columnMap = $sheetDef['columnMap'] ?? [];
                $rows = $sheetDef['rows'] ?? collect();

                if ($index === 0) {
                    $sheet = $spreadsheet->getActiveSheet();
                } else {
                    $sheet = $spreadsheet->createSheet($index);
                }

                $sheet->setTitle($this->sanitizeSheetTitle($sheetTitle));
                $this->populateSheet($sheet, $columnMap, $rows);
                $this->attachChart($sheet, $sheetDef, $columnMap, $rows);
            }

            $spreadsheet->setActiveSheetIndex(0);

            return $this->streamResponse($spreadsheet, $filename);
        } catch (\Throwable $e) {
            Log::error('DataExportService::generateMultiSheet failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse();
        }
    }

    // -------------------------------------------------------------------------
    // File-based exports (for queue jobs that write to disk then upload to S3)
    // -------------------------------------------------------------------------

    /**
     * Generate a single-sheet XLSX to a local file path.
     */
    public function generateSingleSheetToFile(
        string $title,
        array $columnMap,
        Collection $rows,
        string $filePath
    ): void {
        $spreadsheet = $this->createSpreadsheet($title.'.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetTitle($title));

        $this->populateSheet($sheet, $columnMap, $rows);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }

    /**
     * Generate a multi-sheet XLSX to a local file path.
     */
    public function generateMultiSheetToFile(
        array $sheets,
        string $filePath
    ): void {
        $spreadsheet = $this->createSpreadsheet('export.xlsx');

        foreach ($sheets as $index => $sheetDef) {
            $sheetTitle = $sheetDef['title'] ?? ('Sheet '.($index + 1));
            $columnMap = $sheetDef['columnMap'] ?? [];
            $rows = $sheetDef['rows'] ?? collect();

            if ($index === 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet($index);
            }

            $sheet->setTitle($this->sanitizeSheetTitle($sheetTitle));
            $this->populateSheet($sheet, $columnMap, $rows);
            $this->attachChart($sheet, $sheetDef, $columnMap, $rows);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $writer->save($filePath);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createSpreadsheet(string $filename): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('Bayanihan One Window')
            ->setTitle($filename);

        return $spreadsheet;
    }

    private function populateSheet(Worksheet $sheet, array $columnMap, Collection $rows): void
    {
        $columnCount = count($columnMap);

        if ($columnCount === 0) {
            return;
        }

        // Write header row
        foreach ($columnMap as $colIndex => $colDef) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $this->writeSafeCell($sheet, $colLetter.'1', $colDef['label'] ?? '');
        }

        // Style header row
        $lastCol = Coordinate::stringFromColumnIndex($columnCount);
        $this->applyHeaderStyle($sheet, 'A1:'.$lastCol.'1');

        // Freeze header row so it stays visible on scroll
        $sheet->freezePane('A2');

        // Write data rows. Values only — every fill is applied afterwards to a
        // range or as a conditional rule, never per cell.
        $rowIndex = 2;
        $widthSample = [];
        foreach ($rows as $row) {
            $this->writeDataRow($sheet, $columnMap, $row, $rowIndex, $widthSample);
            $rowIndex++;
        }

        $lastRow = $rowIndex - 1;

        if ($lastRow >= 2) {
            $this->applyBandingAndStatusColours($sheet, $columnMap, $lastCol, $lastRow);
            $this->applyNumberFormats($sheet, $columnMap, $lastRow);
        }

        $this->applyColumnWidths($sheet, $columnMap, $widthSample);
    }

    /**
     * Write one row's values.
     *
     * Deliberately does no styling. The previous implementation called
     * getStyle($cellRef)->getFill() for every cell, which allocated a style
     * object per cell — a 10,000-row detail sheet meant ~100,000 of them, and
     * combined with setAutoSize() on every column it exhausted production's
     * 256M limit and blew the 60s request timeout well before the configured
     * row cap was reached.
     *
     * @param  array<int, int>  $widthSample  Longest rendered value seen per column,
     *                                        sampled to size columns without autosize.
     */
    private function writeDataRow(
        Worksheet $sheet,
        array $columnMap,
        mixed $row,
        int $rowIndex,
        array &$widthSample
    ): void {
        $sampling = $rowIndex <= self::WIDTH_SAMPLE_ROWS;

        foreach ($columnMap as $colIndex => $colDef) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $cellRef = $colLetter.$rowIndex;
            $type = $colDef['type'] ?? 'string';
            $key = $colDef['key'] ?? '';

            // Resolve value — supports Eloquent model objects and plain arrays
            $value = is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);

            $rendered = $this->writeTypedCell($sheet, $cellRef, $type, $value);

            if ($sampling) {
                $widthSample[$colIndex] = max(
                    $widthSample[$colIndex] ?? 0,
                    mb_strlen($rendered)
                );
            }
        }
    }

    /**
     * Write a single cell according to its declared type.
     *
     * Numeric, date and percentage columns are written as real Excel values so
     * the workbook can be sorted, summed and pivoted. Everything else goes
     * through writeSafeCell, which keeps the formula-injection guard.
     *
     * @return string The rendered text, used only for column width sampling.
     */
    private function writeTypedCell(Worksheet $sheet, string $cellRef, string $type, mixed $value): string
    {
        switch ($type) {
            case 'uuid':
                // Force string to prevent Excel converting to scientific notation
                $text = (string) ($value ?? '');
                $this->writeSafeCell($sheet, $cellRef, $text);

                return $text;

            case 'date':
            case 'datetime':
                $text = $this->formatDateValue($value, $type === 'datetime');
                $this->writeSafeCell($sheet, $cellRef, $text);

                return $text;

            case 'int':
                if ($value === null || $value === '') {
                    $sheet->setCellValue($cellRef, null);

                    return '';
                }
                $sheet->getCell($cellRef)->setValueExplicit((int) $value, DataType::TYPE_NUMERIC);

                return (string) (int) $value;

            case 'float':
            case 'percent':
                if ($value === null || $value === '') {
                    $sheet->setCellValue($cellRef, null);

                    return '';
                }
                $number = (float) $value;
                // Excel percentage cells hold the fraction, not the display value.
                $stored = $type === 'percent' ? $number / 100 : $number;
                $sheet->getCell($cellRef)->setValueExplicit($stored, DataType::TYPE_NUMERIC);

                return (string) $number;

            default:
                $text = (string) ($value ?? '');
                $this->writeSafeCell($sheet, $cellRef, $value ?? '');

                return $text;
        }
    }

    /**
     * Apply row banding and status colours as conditional formatting.
     *
     * One rule per sheet for the banding and one per status value, instead of
     * one fill per cell. The rendered workbook looks the same; the memory cost
     * stops scaling with row count.
     */
    private function applyBandingAndStatusColours(
        Worksheet $sheet,
        array $columnMap,
        string $lastCol,
        int $lastRow
    ): void {
        $makeBanding = function (): Conditional {
            $banding = new Conditional;
            $banding->setConditionType(Conditional::CONDITION_EXPRESSION)
                ->setConditions(['MOD(ROW(),2)=0']);
            $banding->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::COLOR_ROW_EVEN);

            return $banding;
        };

        // Status columns are registered FIRST. PhpSpreadsheet assigns rule
        // priority in registration order and Excel resolves priority 1 before
        // priority 2, so registering the whole-range banding first would let
        // `MOD(ROW(),2)=0` win on every even row and swallow the status colour
        // there. The old per-cell implementation replaced the band
        // unconditionally; this ordering reproduces that.
        $statusColumns = [];

        foreach ($columnMap as $colIndex => $colDef) {
            if (($colDef['type'] ?? 'string') !== 'status') {
                continue;
            }

            $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $statusColumns[] = $colLetter;

            $rules = [];
            foreach (self::STATUS_COLORS as $status => $argb) {
                $rule = new Conditional;
                $rule->setConditionType(Conditional::CONDITION_CELLIS)
                    ->setOperatorType(Conditional::OPERATOR_EQUAL)
                    ->setConditions(['"'.$status.'"']);
                $rule->getStyle()->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($argb);
                $rules[] = $rule;
            }

            // Banding still applies to rows whose status has no colour.
            $rules[] = $makeBanding();
            $sheet->getStyle($colLetter.'2:'.$colLetter.$lastRow)->setConditionalStyles($rules);
        }

        $sheet->getStyle('A2:'.$lastCol.$lastRow)->setConditionalStyles([$makeBanding()]);
    }

    /**
     * Embed a native Excel chart when the sheet definition asks for one.
     *
     * Native rather than an image: the chart stays bound to the cells, so it
     * updates if the reader filters or edits the data. Charts are opt-in per
     * sheet — the headline sections only — because a chart on a 10,000-row
     * detail sheet is meaningless and the chart writer is the least
     * battle-tested part of PhpSpreadsheet.
     *
     * A chart failure must never cost the workbook: the export is the
     * deliverable, the chart is decoration.
     */
    private function attachChart(Worksheet $sheet, array $sheetDef, array $columnMap, iterable $rows): void
    {
        $type = $sheetDef['chart'] ?? null;

        if ($type === null || count($columnMap) < 2) {
            return;
        }

        $rowCount = is_countable($rows) ? count($rows) : 0;

        if ($rowCount < 2 || $rowCount > self::MAX_CHART_ROWS) {
            return;
        }

        try {
            $title = $sheetDef['title'] ?? 'Chart';
            $sheetRef = "'".str_replace("'", "''", $this->sanitizeSheetTitle($title))."'";
            $lastRow = $rowCount + 1;

            $categories = [new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                $sheetRef.'!$A$2:$A$'.$lastRow,
                null,
                $rowCount
            )];

            // Every numeric column becomes a series, not just column B — the
            // Trends sheet carries Cases and Referrals side by side and
            // charting only the first silently dropped half the data. A pie
            // chart can only show one series, so it keeps the first.
            $numericColumns = [];
            foreach ($columnMap as $colIndex => $colDef) {
                if ($colIndex === 0) {
                    continue; // category axis
                }
                if (in_array($colDef['type'] ?? 'string', ['int', 'float', 'percent'], true)) {
                    $numericColumns[] = Coordinate::stringFromColumnIndex($colIndex + 1);
                }
            }

            if ($numericColumns === []) {
                return;
            }

            if ($type === 'pie') {
                $numericColumns = [reset($numericColumns)];
            }

            $values = [];
            $labels = [];
            foreach ($numericColumns as $letter) {
                $values[] = new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_NUMBER,
                    $sheetRef.'!$'.$letter.'$2:$'.$letter.'$'.$lastRow,
                    null,
                    $rowCount
                );
                $labels[] = new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    $sheetRef.'!$'.$letter.'$1',
                    null,
                    1
                );
            }

            [$seriesType, $grouping] = match ($type) {
                'pie' => [DataSeries::TYPE_PIECHART, null],
                'line' => [DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD],
                default => [DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED],
            };

            $series = new DataSeries(
                $seriesType,
                $grouping,
                range(0, count($values) - 1),
                $labels,
                $categories,
                $values
            );

            if ($seriesType === DataSeries::TYPE_BARCHART) {
                $series->setPlotDirection(DataSeries::DIRECTION_COL);
            }

            $chart = new Chart(
                'chart_'.Str::slug($title),
                new Title($title),
                $seriesType === DataSeries::TYPE_PIECHART ? new Legend(Legend::POSITION_RIGHT, null, false) : null,
                new PlotArea(null, [$series])
            );

            // Park the chart clear of the data columns.
            $anchorColumn = Coordinate::stringFromColumnIndex(count($columnMap) + 2);
            $chart->setTopLeftPosition($anchorColumn.'2');
            $chart->setBottomRightPosition($anchorColumn.'20');

            $sheet->addChart($chart);
        } catch (\Throwable $e) {
            Log::warning('DataExportService: chart skipped', [
                'sheet' => $sheetDef['title'] ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Number formats are applied once per column range, not per cell.
     */
    private function applyNumberFormats(Worksheet $sheet, array $columnMap, int $lastRow): void
    {
        foreach ($columnMap as $colIndex => $colDef) {
            $format = match ($colDef['type'] ?? 'string') {
                'int' => NumberFormat::FORMAT_NUMBER,
                'float' => '#,##0.0',
                'percent' => NumberFormat::FORMAT_PERCENTAGE_00,
                default => null,
            };

            if ($format === null) {
                continue;
            }

            $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->getStyle($colLetter.'2:'.$colLetter.$lastRow)
                ->getNumberFormat()
                ->setFormatCode($format);
        }
    }

    /**
     * Size columns from a sample of the data instead of autosizing.
     *
     * setAutoSize(true) makes PhpSpreadsheet measure every cell in the column
     * at save time. On the detail sheets that was the single largest
     * contributor to export time.
     *
     * @param  array<int, int>  $widthSample
     */
    private function applyColumnWidths(Worksheet $sheet, array $columnMap, array $widthSample): void
    {
        foreach ($columnMap as $colIndex => $colDef) {
            $headerLength = mb_strlen((string) ($colDef['label'] ?? ''));
            $width = max($headerLength, $widthSample[$colIndex] ?? 0) + 2;

            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex + 1))
                ->setWidth(max(self::MIN_COLUMN_WIDTH, min(self::MAX_COLUMN_WIDTH, $width)));
        }
    }

    private function applyHeaderStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['argb' => self::COLOR_HEADER_FONT],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_HEADER_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function formatDateValue(mixed $value, bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $format = $withTime ? 'Y-m-d H:i' : 'Y-m-d';

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        $str = (string) $value;

        if ($withTime) {
            // Normalise "2024-01-15T10:00:00Z" and "2024-01-15 10:00:00" alike.
            return strlen($str) >= 16 ? substr(str_replace('T', ' ', $str), 0, 16) : $str;
        }

        // Trim datetime string to date-only portion (e.g. "2024-01-15 10:00:00" → "2024-01-15")
        if (strlen($str) > 10 && (str_contains($str, ' ') || str_contains($str, 'T'))) {
            return substr($str, 0, 10);
        }

        return $str;
    }

    private function writeSafeCell(Worksheet $sheet, string $cellRef, mixed $value): void
    {
        if (is_string($value)) {
            $safeValue = preg_match('/^\s*[=+\-@]/', $value) ? "'".$value : $value;
            $sheet->getCell($cellRef)->setValueExplicit($safeValue, DataType::TYPE_STRING);

            return;
        }

        $sheet->setCellValue($cellRef, $value);
    }

    private function streamResponse(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        // Ensure .xlsx extension
        if (! str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        // Sanitize filename for use in Content-Disposition header
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename);

        return new StreamedResponse(
            function () use ($spreadsheet) {
                // This runs after the 200 and the headers are already on the
                // wire, so nothing here can change the status code. The
                // try/catch exists only so a write failure is recorded rather
                // than disappearing into a truncated download.
                try {
                    $writer = new Xlsx($spreadsheet);
                    // Charts are only written when explicitly enabled; without
                    // this the embedded charts are silently dropped on download
                    // while the file-based export keeps them.
                    $writer->setIncludeCharts(true);
                    $writer->save('php://output');
                } catch (\Throwable $e) {
                    Log::error('DataExportService: workbook write failed mid-stream', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$safeFilename.'"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    private function errorResponse(): StreamedResponse
    {
        return new StreamedResponse(
            function () {
                echo json_encode([
                    'error' => 'Export failed. Please try again.',
                    'detail' => 'The export encountered an error and could not be completed.',
                ]);
            },
            500,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Excel sheet names: max 31 chars; forbidden chars: \ / ? * [ ] :
     */
    private function sanitizeSheetTitle(string $title): string
    {
        $sanitized = preg_replace('/[\/\\\?\*\[\]:]/', '', $title);

        return substr($sanitized, 0, 31);
    }
}
