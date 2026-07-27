<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Metadata rendered when someone pastes a link to this service.
 *
 * These tags are easy to lose in a template refactor and the loss is silent — the
 * page still works, links just stop previewing, and nobody notices until a link
 * is shared publicly. Hence tests.
 */
class LinkPreviewMetadataTest extends TestCase
{
    private function landing(): string
    {
        return $this->get('/login')->assertStatus(200)->getContent();
    }

    #[Test]
    public function it_renders_a_description(): void
    {
        $html = $this->landing();

        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString(e(config('app.description')), $html);
    }

    #[Test]
    public function it_renders_the_open_graph_tags_platforms_require(): void
    {
        $html = $this->landing();

        foreach ([
            'og:site_name',
            'og:type',
            'og:title',
            'og:description',
            'og:url',
            'og:image',
            'og:image:width',
            'og:image:height',
        ] as $property) {
            $this->assertStringContainsString('property="'.$property.'"', $html, "missing {$property}");
        }
    }

    #[Test]
    public function the_social_image_is_an_absolute_url(): void
    {
        // A relative og:image is rejected outright by Facebook, LinkedIn and
        // Slack — they render no preview at all, which looks identical to having
        // no tags in the first place.
        $html = $this->landing();

        $this->assertMatchesRegularExpression(
            '#<meta property="og:image" content="https?://[^"]+/og-image\.png">#',
            $html
        );
    }

    #[Test]
    public function it_requests_a_large_twitter_card(): void
    {
        // The default `summary` card crops to a small square and wastes a
        // 1200x630 image.
        $html = $this->landing();

        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);
        $this->assertStringContainsString('name="twitter:image"', $html);
    }

    #[Test]
    public function it_renders_a_canonical_url(): void
    {
        $this->assertStringContainsString('rel="canonical"', $this->landing());
    }

    #[Test]
    public function it_links_both_favicon_formats_and_a_touch_icon(): void
    {
        $html = $this->landing();

        $this->assertStringContainsString('href="/favicon.svg"', $html);
        // Crawlers and older clients request /favicon.ico by convention.
        $this->assertStringContainsString('href="/favicon.ico"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
    }

    #[Test]
    public function the_robots_meta_agrees_with_the_indexing_switch(): void
    {
        // The meta tag and the X-Robots-Tag header are driven by one config flag
        // precisely so they cannot contradict each other.
        config(['app.search_indexing_enabled' => false]);
        $this->assertStringContainsString(
            '<meta name="robots" content="noindex, nofollow">',
            $this->landing()
        );

        config(['app.search_indexing_enabled' => true]);
        $this->assertStringContainsString(
            '<meta name="robots" content="index, follow">',
            $this->landing()
        );
    }

    #[Test]
    public function the_social_preview_assets_exist_on_disk(): void
    {
        // Tags pointing at a 404 produce no preview, and the previous
        // favicon.ico was a zero-byte file.
        foreach (['og-image.png', 'apple-touch-icon.png', 'favicon.ico', 'favicon.svg'] as $asset) {
            $path = public_path($asset);
            $this->assertFileExists($path);
            $this->assertGreaterThan(0, filesize($path), "{$asset} is empty");
        }
    }
}
