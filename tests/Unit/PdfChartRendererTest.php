<?php

namespace Tests\Unit;

use App\Services\Reports\PdfChartRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfChartRendererTest extends TestCase
{
    private PdfChartRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new PdfChartRenderer;
    }

    private function pngData(string $html): \GdImage|false
    {
        if (! preg_match('/data:image\/png;base64,([A-Za-z0-9+\/=]+)/', $html, $m)) {
            return false;
        }

        return imagecreatefromstring(base64_decode($m[1], true));
    }

    /**
     * Regression: production ran a GD build without FreeType, so
     * imagettftext() was undefined and every chart call fatalled — taking the
     * whole Reports PDF export down with a 500 for nine days. This suite could
     * not see it, because every host it runs on has FreeType. Forcing the
     * capability off is the only way to exercise the production path here.
     */
    #[Test]
    public function every_chart_type_renders_when_gd_lacks_freetype(): void
    {
        $renderer = new class extends PdfChartRenderer
        {
            public static function hasTrueType(): bool
            {
                return false;
            }
        };

        $charts = [
            'bar' => $renderer->barChart(['Jan', 'Feb'], [10, 20]),
            'line' => $renderer->lineChart(['Jan', 'Feb'], [10, 20]),
            'pie' => $renderer->pieChart(['Open', 'Closed'], [3, 7]),
            'hbar' => $renderer->horizontalBarChart(['Cebu', 'Bohol'], [40, 12]),
        ];

        foreach ($charts as $type => $html) {
            $this->assertStringStartsWith(
                '<img src="data:image/png;base64,',
                $html,
                "{$type} chart did not render without FreeType"
            );
            $this->assertNotFalse($this->pngData($html), "{$type} chart produced an unreadable PNG");
        }
    }

    #[Test]
    public function has_true_type_reports_this_builds_capability(): void
    {
        $this->assertSame(
            function_exists('imagettftext') && function_exists('imagettfbbox'),
            PdfChartRenderer::hasTrueType()
        );
    }

    #[Test]
    public function bar_chart_returns_valid_png(): void
    {
        $html = $this->renderer->barChart(
            ['Jan', 'Feb', 'Mar'],
            [100, 200, 150],
        );

        $this->assertStringStartsWith('<img src="data:image/png;base64,', $html);
        $this->assertStringContainsString('width="460"', $html);
        $this->assertStringContainsString('height="200"', $html);

        $img = $this->pngData($html);
        $this->assertNotFalse($img);
        $this->assertSame(460, imagesx($img));
        $this->assertSame(200, imagesy($img));
    }

    #[Test]
    public function line_chart_returns_valid_png(): void
    {
        $html = $this->renderer->lineChart(
            ['2026-01', '2026-02', '2026-03'],
            [10, 25, 18],
        );

        $this->assertStringStartsWith('<img src="data:image/png;base64,', $html);
        $this->assertStringContainsString('width="460"', $html);
        $this->assertStringContainsString('height="160"', $html);

        $img = $this->pngData($html);
        $this->assertNotFalse($img);
        $this->assertSame(460, imagesx($img));
        $this->assertSame(160, imagesy($img));
    }

    #[Test]
    public function pie_chart_returns_valid_png(): void
    {
        $html = $this->renderer->pieChart(
            ['Completed', 'Pending', 'Processing'],
            [50, 30, 20],
        );

        $this->assertStringStartsWith('<img src="data:image/png;base64,', $html);
        $this->assertStringContainsString('width="240"', $html);

        // 240px pie + 8px gap + 3 legend rows of 14px + 4px padding.
        $img = $this->pngData($html);
        $this->assertNotFalse($img);
        $this->assertSame(240, imagesx($img));
        $this->assertSame(294, imagesy($img));
    }

    #[Test]
    public function horizontal_bar_chart_returns_valid_png(): void
    {
        $html = $this->renderer->horizontalBarChart(
            ['OWWA', 'DOLE', 'TESDA'],
            [45, 32, 28],
        );

        $this->assertStringStartsWith('<img src="data:image/png;base64,', $html);
        $this->assertStringContainsString('width="460"', $html);

        $img = $this->pngData($html);
        $this->assertNotFalse($img);
        $this->assertSame(460, imagesx($img));
        $this->assertSame(80, imagesy($img));
    }

    #[Test]
    public function horizontal_bar_chart_scales_height_by_row_count(): void
    {
        $html = $this->renderer->horizontalBarChart(
            ['A', 'B', 'C', 'D', 'E', 'F'],
            [45, 32, 28, 20, 15, 10],
        );

        $img = $this->pngData($html);
        $this->assertNotFalse($img);
        $this->assertSame(460, imagesx($img));
        $this->assertSame(132, imagesy($img));
    }

    #[Test]
    public function chart_respects_custom_colors(): void
    {
        $html = $this->renderer->barChart(
            ['A'],
            [10],
            ['color' => '#ff0000'],
        );

        $img = $this->pngData($html);
        $this->assertNotFalse($img);

        // A single max-value bar fills most of the plot area; sample its
        // interior (bar spans x≈98..390, y=10..176 for one bar at 460x200).
        $rgb = imagecolorsforindex($img, imagecolorat($img, 244, 100));
        $this->assertGreaterThan(200, $rgb['red']);
        $this->assertLessThan(80, $rgb['green']);
        $this->assertLessThan(80, $rgb['blue']);
    }

    #[Test]
    public function chart_handles_long_labels_without_error(): void
    {
        $html = $this->renderer->barChart(
            ['Very Long Category Name That Exceeds Limit'],
            [100],
        );

        $this->assertNotFalse($this->pngData($html));
    }

    #[Test]
    public function horizontal_bar_chart_returns_empty_for_empty_data(): void
    {
        $this->assertSame('', $this->renderer->horizontalBarChart([], []));
        $this->assertSame('', $this->renderer->horizontalBarChart([], [1, 2]));
        $this->assertSame('', $this->renderer->horizontalBarChart(['a'], []));
    }

    #[Test]
    public function bar_chart_returns_empty_for_empty_data(): void
    {
        $this->assertSame('', $this->renderer->barChart([], []));
        $this->assertSame('', $this->renderer->barChart([], [1, 2]));
        $this->assertSame('', $this->renderer->barChart(['a'], []));
    }

    #[Test]
    public function line_chart_returns_empty_for_empty_data(): void
    {
        $this->assertSame('', $this->renderer->lineChart([], []));
    }

    #[Test]
    public function pie_chart_returns_empty_for_empty_data(): void
    {
        $this->assertSame('', $this->renderer->pieChart([], []));
    }

    #[Test]
    public function pie_chart_returns_empty_for_zero_sum(): void
    {
        $this->assertSame('', $this->renderer->pieChart(['A', 'B'], [0, 0]));
    }
}
