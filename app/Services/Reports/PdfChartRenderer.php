<?php

namespace App\Services\Reports;

use Maantje\Charts\Bar\Bar;
use Maantje\Charts\Bar\Bars;
use Maantje\Charts\Chart;
use Maantje\Charts\Grid;
use Maantje\Charts\Line\Line;
use Maantje\Charts\Line\Lines;
use Maantje\Charts\Line\Point;
use Maantje\Charts\Pie\PieChart;
use Maantje\Charts\Pie\Slice;
use Maantje\Charts\XAxis;
use Maantje\Charts\YAxis;

class PdfChartRenderer
{
    public const PALETTE = [
        '#005288', // DMW blue
        '#3f915f', // green
        '#0b7a75', // teal
        '#9b51b0', // purple
        '#d9663b', // orange
        '#2563eb', // blue
        '#7c3aed', // violet
        '#dc2626', // red
    ];

    /**
     * Render a vertical bar chart as SVG string.
     */
    public function barChart(array $labels, array $data, array $options = []): string
    {
        if (empty($labels) || empty($data)) {
            return '';
        }

        $width = $options['width'] ?? 460;
        $height = $options['height'] ?? 200;
        $color = $options['color'] ?? self::PALETTE[0];

        $bars = [];
        foreach ($labels as $i => $label) {
            $bars[] = new Bar(
                name: $this->truncateLabel($label, 12),
                value: (float) ($data[$i] ?? 0),
                color: $options['colors'][$i] ?? $color,
            );
        }

        $chart = new Chart(
            width: $width,
            height: $height,
            background: 'white',
            fontSize: 9,
            fontFamily: 'DejaVu Sans',
            grid: new Grid(lineColor: '#e2e8f0'),
            yAxis: new YAxis(minValue: 0),
            xAxis: new XAxis(fontSize: 8),
            series: [new Bars(bars: $bars)],
        );

        return $chart->render();
    }

    /**
     * Render a line chart as SVG string.
     */
    public function lineChart(array $labels, array $data, array $options = []): string
    {
        if (empty($labels) || empty($data)) {
            return '';
        }

        $width = $options['width'] ?? 460;
        $height = $options['height'] ?? 200;
        $color = $options['color'] ?? self::PALETTE[0];

        $points = [];
        foreach ($data as $i => $value) {
            $points[] = new Point(x: $i, y: (float) $value);
        }

        $chart = new Chart(
            width: $width,
            height: $height,
            background: 'white',
            fontSize: 9,
            fontFamily: 'DejaVu Sans',
            grid: new Grid(lineColor: '#e2e8f0'),
            yAxis: new YAxis(minValue: 0),
            xAxis: new XAxis(fontSize: 8),
            series: [
                new Lines(lines: [
                    new Line(points: $points, color: $color, size: 2),
                ]),
            ],
        );

        return $chart->render();
    }

    /**
     * Render a pie/donut chart as SVG string.
     */
    public function pieChart(array $labels, array $data, array $options = []): string
    {
        if (empty($labels) || empty($data)) {
            return '';
        }

        $size = $options['size'] ?? 240;
        $total = array_sum($data);
        if ($total === 0) {
            return '';
        }

        $slices = [];
        foreach ($labels as $i => $label) {
            $slices[] = new Slice(
                value: (float) ($data[$i] ?? 0),
                color: $options['colors'][$i] ?? self::PALETTE[$i % count(self::PALETTE)],
                label: $this->truncateLabel($label, 15),
                fontSize: 9,
            );
        }

        $pie = new PieChart(
            size: $size,
            slices: $slices,
            background: 'white',
            fontSize: 9,
            fontFamily: 'DejaVu Sans',
        );

        return $pie->render();
    }

    /**
     * Render a horizontal bar chart as SVG string.
     * (Implemented as a standard vertical bar chart with rotated logic via CSS in the PDF)
     */
    public function horizontalBarChart(array $labels, array $data, array $options = []): string
    {
        if (empty($labels) || empty($data)) {
            return '';
        }

        $width = $options['width'] ?? 460;
        $height = $options['height'] ?? max(150, count($labels) * 28);
        $color = $options['color'] ?? self::PALETTE[0];

        // For horizontal bars, we render them as a vertical bar chart
        // DomPDF doesn't support CSS transforms on SVG, so we use the same bar chart
        // with shorter labels and the visual reads as a comparison
        $bars = [];
        foreach ($labels as $i => $label) {
            $bars[] = new Bar(
                name: $this->truncateLabel($label, 18),
                value: (float) ($data[$i] ?? 0),
                color: $options['colors'][$i] ?? $color,
            );
        }

        $chart = new Chart(
            width: $width,
            height: $height,
            background: 'white',
            fontSize: 9,
            fontFamily: 'DejaVu Sans',
            grid: new Grid(lineColor: '#e2e8f0'),
            yAxis: new YAxis(minValue: 0),
            xAxis: new XAxis(fontSize: 7),
            series: [new Bars(bars: $bars)],
        );

        return $chart->render();
    }

    /**
     * Truncate a label string to maxLen characters with ellipsis.
     */
    private function truncateLabel(string $label, int $maxLen): string
    {
        if (mb_strlen($label) <= $maxLen) {
            return $label;
        }

        return mb_substr($label, 0, $maxLen - 1).'…';
    }
}
