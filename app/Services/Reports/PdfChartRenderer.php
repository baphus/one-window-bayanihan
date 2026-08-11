<?php

namespace App\Services\Reports;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;

/**
 * Render report charts as PNG images embedded as base64 data URIs.
 *
 * Charts are drawn directly with the GD extension and returned as
 * `<img src="data:image/png;base64,...">` strings. dompdf (the PDF engine used
 * for the report export) does not render inline `<svg>` markup — it drops the
 * geometry and leaks the SVG `<text>` nodes as stray text above the data
 * tables. Drawing the charts with GD and embedding them as PNG images is the
 * only reliable path, because `config/dompdf.php` already whitelists the
 * `data://` protocol.
 *
 * Everything is rendered at 2x scale and downsampled before encoding, which
 * gives the GD primitives (which have no native anti-aliasing) smooth edges.
 */
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

    private const SCALE = 2;

    private ?string $font = null;

    /**
     * Render a vertical bar chart as a base64 PNG <img> tag.
     */
    public function barChart(array $labels, array $data, array $options = []): string
    {
        if (empty($labels) || empty($data)) {
            return '';
        }

        $width = (int) ($options['width'] ?? 460);
        $height = (int) ($options['height'] ?? 200);
        $color = $options['color'] ?? self::PALETTE[0];
        $colors = $options['colors'] ?? [];

        $x0 = 36;
        $x1 = $width - 8;
        $y0 = 10;
        $y1 = $height - 24;
        $plotW = $x1 - $x0;
        $plotH = $y1 - $y0;

        $niceMax = $this->niceMax(max(0.0, ...array_map(fn ($v) => (float) $v, $data)));

        $img = $this->baseImage($width, $height);
        $grid = $this->allocateColor($img, '#e2e8f0');
        $axis = $this->allocateColor($img, '#94a3b8');
        $text = $this->allocateColor($img, '#334155');

        $this->drawGrid($img, $x0, $x1, $y0, $y1, $niceMax, $axis, $grid);

        $n = count($labels);
        $slotW = $plotW / $n;
        $barW = max(2.0, $slotW * 0.7);

        foreach ($labels as $i => $label) {
            $value = (float) ($data[$i] ?? 0);
            $cx = $x0 + $slotW * ($i + 0.5);
            $barH = $niceMax > 0 ? ($value / $niceMax) * $plotH : 0;
            $rx = $cx - $barW / 2;
            $ry = $y1 - $barH;

            imagefilledrectangle(
                $img,
                (int) round($rx * self::SCALE),
                (int) round($ry * self::SCALE),
                (int) round(($rx + $barW) * self::SCALE),
                (int) round($y1 * self::SCALE),
                $this->allocateColor($img, $colors[$i] ?? $color)
            );

            if ($barH >= 14) {
                $this->drawText($img, 7, $cx, $ry - 3, (string) round($value), $text, centerX: true);
            }

            $this->drawText($img, 7, $cx, $y1 + 13, $this->truncateLabel($label, $n > 12 ? 6 : 10), $text, centerX: true);
        }

        return $this->toDataUri($img, $width, $height);
    }

    /**
     * Render a line chart as a base64 PNG <img> tag.
     */
    public function lineChart(array $labels, array $data, array $options = []): string
    {
        if (empty($labels) || empty($data)) {
            return '';
        }

        $width = (int) ($options['width'] ?? 460);
        $height = (int) ($options['height'] ?? 160);
        $color = $options['color'] ?? self::PALETTE[0];

        $x0 = 36;
        $x1 = $width - 8;
        $y0 = 10;
        $y1 = $height - 22;
        $plotW = $x1 - $x0;
        $plotH = $y1 - $y0;

        $niceMax = $this->niceMax(max(0.0, ...array_map(fn ($v) => (float) $v, $data)));

        $img = $this->baseImage($width, $height);
        $axis = $this->allocateColor($img, '#94a3b8');
        $text = $this->allocateColor($img, '#334155');
        $line = $this->allocateColor($img, $color);

        $this->drawGrid($img, $x0, $x1, $y0, $y1, $niceMax, $axis);

        $n = count($data);
        $points = [];
        foreach ($data as $i => $value) {
            $px = $n === 1 ? $x0 + $plotW / 2 : $x0 + $plotW * $i / ($n - 1);
            $py = $y1 - ($niceMax > 0 ? ((float) $value / $niceMax) * $plotH : 0);
            $points[] = [$px, $py];
        }

        imagesetthickness($img, 2);
        for ($i = 1; $i < $n; $i++) {
            imageline(
                $img,
                (int) round($points[$i - 1][0] * self::SCALE),
                (int) round($points[$i - 1][1] * self::SCALE),
                (int) round($points[$i][0] * self::SCALE),
                (int) round($points[$i][1] * self::SCALE),
                $line
            );
        }
        imagesetthickness($img, 1);

        foreach ($points as [$px, $py]) {
            imagefilledellipse(
                $img,
                (int) round($px * self::SCALE),
                (int) round($py * self::SCALE),
                (int) round(5 * self::SCALE),
                (int) round(5 * self::SCALE),
                $line
            );
        }

        $step = (int) ceil($n / 12);
        foreach ($labels as $i => $label) {
            if ($i % $step !== 0 && $i !== $n - 1) {
                continue;
            }
            $this->drawText($img, 7, $points[$i][0], $y1 + 13, $this->truncateLabel($label, 8), $text, centerX: true);
        }

        return $this->toDataUri($img, $width, $height);
    }

    /**
     * Render a pie chart with a legend below it as a base64 PNG <img> tag.
     */
    public function pieChart(array $labels, array $data, array $options = []): string
    {
        if (empty($labels) || empty($data)) {
            return '';
        }

        $total = array_sum(array_map(fn ($v) => (float) $v, $data));
        if ($total <= 0) {
            return '';
        }

        $size = (int) ($options['size'] ?? 240);
        $colors = $options['colors'] ?? [];

        $slices = [];
        foreach ($labels as $i => $label) {
            $value = (float) ($data[$i] ?? 0);
            if ($value <= 0) {
                continue;
            }
            $slices[] = [
                'label' => $this->truncateLabel((string) $label, 15),
                'value' => $value,
                'color' => $colors[$i] ?? self::PALETTE[$i % count(self::PALETTE)],
            ];
        }
        if (empty($slices)) {
            return '';
        }

        $rowH = 14;
        $legendTop = $size + 8;
        $height = $legendTop + count($slices) * $rowH + 4;

        $img = $this->baseImage($size, $height);
        $text = $this->allocateColor($img, '#334155');
        $sub = $this->allocateColor($img, '#475569');

        $cx = $size / 2;
        $cy = $size / 2;

        // GD angles: 0° is 3 o'clock, increasing clockwise (270° is 12 o'clock).
        $angle = 270;
        foreach ($slices as $slice) {
            $sweep = ($slice['value'] / $total) * 360;
            $end = $angle + $sweep;
            imagefilledarc(
                $img,
                (int) round($cx * self::SCALE),
                (int) round($cy * self::SCALE),
                (int) round($size * self::SCALE),
                (int) round($size * self::SCALE),
                (int) round($angle),
                (int) round($end),
                $this->allocateColor($img, $slice['color']),
                IMG_ARC_PIE
            );
            $angle = $end;
        }

        // Thin white outline so adjacent slices read as distinct wedges.
        imageellipse(
            $img,
            (int) round($cx * self::SCALE),
            (int) round($cy * self::SCALE),
            (int) round($size * self::SCALE),
            (int) round($size * self::SCALE),
            $this->allocateColor($img, '#ffffff')
        );

        foreach ($slices as $i => $slice) {
            $baseline = $legendTop + $i * $rowH + $rowH - 4;
            imagefilledrectangle(
                $img,
                (int) round(10 * self::SCALE),
                (int) round(($baseline - 7) * self::SCALE),
                (int) round(18 * self::SCALE),
                (int) round(($baseline + 1) * self::SCALE),
                $this->allocateColor($img, $slice['color'])
            );
            $this->drawText($img, 8, 24, $baseline, $slice['label'], $text);

            $pct = ($slice['value'] / $total) * 100;
            $pctStr = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.').'%';
            $this->drawText($img, 8, $size - 6, $baseline, $pctStr, $sub, right: true);
        }

        return $this->toDataUri($img, $size, $height);
    }

    /**
     * Render a horizontal bar chart as a base64 PNG <img> tag.
     *
     * One row per category: truncated label on the left, bar extending to the
     * right, numeric value on the right. Rendering each label on its own row
     * keeps every label readable and prevents the overlapping text that occurs
     * when many labels share a single axis baseline.
     */
    public function horizontalBarChart(array $labels, array $data, array $options = []): string
    {
        if (empty($labels) || empty($data)) {
            return '';
        }

        $width = (float) ($options['width'] ?? 460);
        $height = (float) ($options['height'] ?? max(80, count($labels) * 22));
        $color = $options['color'] ?? self::PALETTE[0];
        $colors = $options['colors'] ?? [];

        $labelColumn = 130.0;
        $valueColumn = 44.0;
        $barX = $labelColumn + 6;
        $barAreaWidth = max(1.0, $width - $barX - $valueColumn);
        $maxValue = max(0.0, ...array_map(fn ($v) => (float) $v, $data));
        $rowCount = count($labels);
        $rowHeight = max(14.0, ($height - 12) / $rowCount);

        $img = $this->baseImage((int) round($width), (int) round($height));
        $text = $this->allocateColor($img, '#334155');

        foreach ($labels as $i => $label) {
            $value = (float) ($data[$i] ?? 0);
            $centerY = 6 + ($i + 0.5) * $rowHeight;
            $barWidth = $maxValue > 0 ? max(2.0, ($value / $maxValue) * $barAreaWidth) : 2.0;

            $this->drawText($img, 8, 2, $centerY + 3, $this->truncateLabel((string) $label, 18), $text);

            imagefilledrectangle(
                $img,
                (int) round($barX * self::SCALE),
                (int) round(($centerY - 4) * self::SCALE),
                (int) round(($barX + $barWidth) * self::SCALE),
                (int) round(($centerY + 4) * self::SCALE),
                $this->allocateColor($img, $colors[$i] ?? $color)
            );

            $this->drawText($img, 8, $width - 2, $centerY + 3, number_format($value), $text, right: true);
        }

        return $this->toDataUri($img, (int) round($width), (int) round($height));
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

    /**
     * Pick a "nice" round maximum (1/2/5 × power of 10) above the raw max so
     * gridline labels read cleanly.
     */
    private function niceMax(float $rawMax): float
    {
        if ($rawMax <= 0) {
            return 1.0;
        }

        $magnitude = pow(10, floor(log10($rawMax)));
        $norm = $rawMax / $magnitude;
        $nice = $norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 5 ? 5 : 10));

        return $nice * $magnitude;
    }

    /**
     * Create a white 2x canvas for the given 1x dimensions.
     */
    private function baseImage(int $width, int $height): \GdImage
    {
        $img = imagecreatetruecolor($width * self::SCALE, $height * self::SCALE);
        imagefill($img, 0, 0, $this->allocateColor($img, '#ffffff'));

        return $img;
    }

    /**
     * Draw 4 horizontal gridlines with numeric y-axis labels (niceMax at top).
     */
    private function drawGrid(\GdImage $img, int $x0, int $x1, int $y0, int $y1, float $niceMax, int $labelColor, ?int $lineColor = null): void
    {
        $lineColor ??= $labelColor;

        for ($i = 0; $i <= 4; $i++) {
            $y = $y0 + ($y1 - $y0) * $i / 4;
            $label = $niceMax * (4 - $i) / 4;

            imageline(
                $img,
                $x0 * self::SCALE,
                (int) round($y * self::SCALE),
                $x1 * self::SCALE,
                (int) round($y * self::SCALE),
                $lineColor
            );
            $this->drawText($img, 7, 2, $y - 2.5, (string) round($label), $labelColor);
        }
    }

    /**
     * Draw text with imagettftext, handling alignment and the 2x scale.
     */
    private function drawText(\GdImage $img, float $px, float $x, float $y, string $text, int $color, bool $centerX = false, bool $right = false): void
    {
        $size = $px * self::SCALE;
        $tx = $x * self::SCALE;
        $ty = $y * self::SCALE;

        // static::, not self:: — self:: is early-bound and would ignore a
        // subclass override, which is the only way a test can exercise the
        // no-FreeType path on a host that has FreeType.
        if (! static::hasTrueType()) {
            $this->drawBitmapText($img, $tx, $ty, $text, $color, $centerX, $right);

            return;
        }

        $font = $this->fontPath();

        if ($centerX || $right) {
            $bbox = imagettfbbox($size, 0, $font, $text);
            $width = $bbox[2] - $bbox[0];
            $tx -= $centerX ? $width / 2 : $width;
        }

        imagettftext($img, $size, 0, (int) round($tx), (int) round($ty), $color, $font, $text);
    }

    /**
     * Whether this PHP build can render TrueType text.
     *
     * imagettftext()/imagettfbbox() only exist when GD was compiled with
     * FreeType. A build without it does not fail at the call — the functions
     * are simply undefined, which takes down the whole export with a fatal
     * error. Charts degrade to GD's bitmap font instead so a missing build
     * flag costs label quality, never the feature.
     */
    public static function hasTrueType(): bool
    {
        return function_exists('imagettftext') && function_exists('imagettfbbox');
    }

    /**
     * Fallback label renderer using GD's built-in bitmap fonts.
     *
     * Font 5 (9x15 px) is the largest built-in and the closest match to the
     * 7-8px @2x labels the TrueType path draws. Bitmap fonts have no baseline
     * concept, so the y coordinate is shifted from baseline to top-left.
     */
    private function drawBitmapText(\GdImage $img, float $tx, float $ty, string $text, int $color, bool $centerX, bool $right): void
    {
        $font = 5;
        $charWidth = imagefontwidth($font);
        $charHeight = imagefontheight($font);

        // Built-in fonts are ASCII only; anything else renders as noise.
        $ascii = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        $width = $charWidth * mb_strlen($ascii, '8bit');

        if ($centerX) {
            $tx -= $width / 2;
        } elseif ($right) {
            $tx -= $width;
        }

        imagestring($img, $font, (int) round($tx), (int) round($ty - $charHeight), $ascii, $color);
    }

    /**
     * Path to the DejaVu Sans TTF shipped with dompdf (used for GD text).
     */
    private function fontPath(): string
    {
        if ($this->font === null) {
            $this->font = '';
            $candidates = [
                __DIR__.'/../../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf',
            ];

            // base_path() fatals when no Laravel Application is bootstrapped
            // (e.g. running the renderer standalone via php -r), so only use it
            // when the container is a real application.
            $container = Container::getInstance();
            if ($container instanceof Application) {
                $candidates[] = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
            }

            foreach ($candidates as $path) {
                if (is_file($path)) {
                    $this->font = $path;
                    break;
                }
            }
        }

        if ($this->font === '') {
            throw new \RuntimeException('DejaVu Sans font not found for PDF chart rendering.');
        }

        return $this->font;
    }

    /**
     * Parse a #RRGGBB hex color into a GD color index.
     */
    private function allocateColor(\GdImage $img, string $hex): int
    {
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $m)) {
            return imagecolorallocate(
                $img,
                hexdec(substr($m[1], 0, 2)),
                hexdec(substr($m[1], 2, 2)),
                hexdec(substr($m[1], 4, 2))
            );
        }

        return imagecolorallocate($img, 148, 163, 184);
    }

    /**
     * Downsample the 2x canvas to 1x, encode as PNG, and return an <img> tag
     * with a base64 data URI. Frees both GD images.
     */
    private function toDataUri(\GdImage $img2x, int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagecopyresampled($img, $img2x, 0, 0, 0, 0, $width, $height, $width * self::SCALE, $height * self::SCALE);

        ob_start();
        imagepng($img);
        $data = (string) ob_get_clean();
        $png = base64_encode($data);

        imagedestroy($img);
        imagedestroy($img2x);

        return '<img src="data:image/png;base64,'.$png.'" width="'.$width.'" height="'.$height.'" style="max-width:100%;"/>';
    }
}
