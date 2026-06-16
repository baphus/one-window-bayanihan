<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

final class ExcelStyles
{
    public static function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B5A8C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
    }

    public static function evenRowStyle(): array
    {
        return [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
        ];
    }

    public static function oddRowStyle(): array
    {
        return [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
        ];
    }

    public static function statusColor(string $status): ?array
    {
        $colors = [
            'COMPLETED' => 'FFD1FAE5',
            'CLOSED' => 'FFD1FAE5',
            'PENDING' => 'FFFEF3C7',
            'PROCESSING' => 'FFDBEAFE',
            'FOR_COMPLIANCE' => 'FFFFEDD5',
            'REJECTED' => 'FFFEE2E2',
        ];

        $key = strtoupper($status);

        if (! isset($colors[$key])) {
            return null;
        }

        return [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors[$key]]],
        ];
    }

    public static function dateFormat(): string
    {
        return 'YYYY-MM-DD';
    }

    public static function textFormat(): string
    {
        return '@';
    }
}
