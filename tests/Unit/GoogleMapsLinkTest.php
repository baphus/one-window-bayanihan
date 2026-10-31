<?php

namespace Tests\Unit;

use App\Rules\GoogleMapsLink;
use PHPUnit\Framework\TestCase;

class GoogleMapsLinkTest extends TestCase
{
    public function test_accepts_only_explicit_google_maps_hosts_and_expected_paths(): void
    {
        foreach ([
            'https://google.com/maps?q=cebu',
            'https://www.google.com/maps?q=cebu',
            'https://maps.google.com/maps?q=cebu',
            'https://google.com.ph/maps?q=cebu',
            'https://www.google.com.ph/maps?q=cebu',
            'https://maps.google.com.ph/maps?q=cebu',
            'https://maps.app.goo.gl/abc123',
            'https://goo.gl/maps/abc123',
        ] as $url) {
            $this->assertValid($url);
        }

        foreach ([
            'https://example.com/maps?q=cebu',
            'https://google.com.evil.example/maps?q=cebu',
            'https://goo.gl/abc123',
            'https://maps.app.goo.gl/',
            'https://maps.app.goo.gl',
            'ftp://google.com/maps?q=cebu',
        ] as $url) {
            $this->assertInvalid($url);
        }
    }

    private function assertValid(string $url): void
    {
        $messages = [];
        (new GoogleMapsLink)->validate('map_link', $url, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $this->assertSame([], $messages, "Unexpected rejection: {$url}");
    }

    private function assertInvalid(string $url): void
    {
        $messages = [];
        (new GoogleMapsLink)->validate('map_link', $url, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $this->assertNotEmpty($messages, "Unexpected acceptance: {$url}");
    }
}
