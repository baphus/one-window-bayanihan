<?php

namespace App\Services\Export;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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

            return $this->errorResponse($e->getMessage());
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
            }

            $spreadsheet->setActiveSheetIndex(0);

            return $this->streamResponse($spreadsheet, $filename);
        } catch (\Throwable $e) {
            Log::error('DataExportService::generateMultiSheet failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse($e->getMessage());
        }
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
            $sheet->setCellValue($colLetter.'1', $colDef['label'] ?? '');
        }

        // Style header row
        $lastCol = Coordinate::stringFromColumnIndex($columnCount);
        $this->applyHeaderStyle($sheet, 'A1:'.$lastCol.'1');

        // Freeze header row so it stays visible on scroll
        $sheet->freezePane('A2');

        // Write data rows
        $rowIndex = 2;
        foreach ($rows as $row) {
            $this->writeDataRow($sheet, $columnMap, $row, $rowIndex);
            $rowIndex++;
        }

        // Auto-size all columns
        for ($i = 1; $i <= $columnCount; $i++) {
            $sheet->getColumnDimension(
                Coordinate::stringFromColumnIndex($i)
            )->setAutoSize(true);
        }
    }

    private function writeDataRow(
        Worksheet $sheet,
        array $columnMap,
        mixed $row,
        int $rowIndex
    ): void {
        $isEven = ($rowIndex % 2 === 0);
        $defaultBg = $isEven ? self::COLOR_ROW_EVEN : self::COLOR_ROW_ODD;

        foreach ($columnMap as $colIndex => $colDef) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
            $cellRef = $colLetter.$rowIndex;
            $type = $colDef['type'] ?? 'string';
            $key = $colDef['key'] ?? '';

            // Resolve value — supports Eloquent model objects and plain arrays
            $value = is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);

            // Write cell with type-specific handling
            switch ($type) {
                case 'uuid':
                    // Force string to prevent Excel converting to scientific notation
                    $sheet->getCell($cellRef)->setValueExplicit(
                        (string) ($value ?? ''),
                        DataType::TYPE_STRING
                    );
                    break;

                case 'date':
                    $sheet->getCell($cellRef)->setValueExplicit(
                        $this->formatDateValue($value),
                        DataType::TYPE_STRING
                    );
                    break;

                default:
                    $sheet->setCellValue($cellRef, $value ?? '');
                    break;
            }

            // Determine background: status-specific override or alternating default
            $bgColor = $defaultBg;
            if ($type === 'status' && $value !== null && $value !== '') {
                $statusKey = strtoupper((string) $value);
                $bgColor = self::STATUS_COLORS[$statusKey] ?? $defaultBg;
            }

            $sheet->getStyle($cellRef)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($bgColor);
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

    private function formatDateValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $str = (string) $value;

        // Trim datetime string to date-only portion (e.g. "2024-01-15 10:00:00" → "2024-01-15")
        if (strlen($str) > 10 && (str_contains($str, ' ') || str_contains($str, 'T'))) {
            return substr($str, 0, 10);
        }

        return $str;
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
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$safeFilename.'"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    private function errorResponse(string $message): StreamedResponse
    {
        return new StreamedResponse(
            function () use ($message) {
                echo json_encode([
                    'error' => 'Export failed. Please try again.',
                    'detail' => $message,
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
