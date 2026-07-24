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

    #[Test]
    public function bar_chart_returns_valid_svg(): void
    {
        $svg = $this->renderer->barChart(
            ['Jan', 'Feb', 'Mar'],
            [100, 200, 150],
        );

        $this->assertNotEmpty($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    #[Test]
    public function line_chart_returns_valid_svg(): void
    {
        $svg = $this->renderer->lineChart(
            ['2026-01', '2026-02', '2026-03'],
            [10, 25, 18],
        );

        $this->assertNotEmpty($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    #[Test]
    public function pie_chart_returns_valid_svg(): void
    {
        $svg = $this->renderer->pieChart(
            ['Completed', 'Pending', 'Processing'],
            [50, 30, 20],
        );

        $this->assertNotEmpty($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    #[Test]
    public function horizontal_bar_chart_returns_valid_svg(): void
    {
        $svg = $this->renderer->horizontalBarChart(
            ['OWWA', 'DOLE', 'TESDA'],
            [45, 32, 28],
        );

        $this->assertNotEmpty($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
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

    #[Test]
    public function chart_respects_custom_colors(): void
    {
        $svg = $this->renderer->barChart(
            ['A', 'B'],
            [10, 20],
            ['color' => '#ff0000'],
        );

        $this->assertStringContainsString('#ff0000', $svg);
    }

    #[Test]
    public function chart_truncates_long_labels(): void
    {
        $svg = $this->renderer->barChart(
            ['Very Long Category Name That Exceeds Limit'],
            [100],
        );

        $this->assertStringContainsString('<svg', $svg);
        // Label should be truncated (12 chars for bar chart)
        $this->assertStringNotContainsString('Very Long Category Name That Exceeds Limit', $svg);
    }
}
