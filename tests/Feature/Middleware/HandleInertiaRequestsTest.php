<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    #[Test]
    public function it_returns_no_asset_version_in_local_environment(): void
    {
        $this->app['env'] = 'local';
        config(['app.asset_url' => 'https://assets.example.test']);

        $version = (new HandleInertiaRequests)->version(Request::create('/'));

        $this->assertNull($version);
    }

    #[Test]
    public function it_uses_the_parent_asset_version_outside_local_environment(): void
    {
        $this->app['env'] = 'testing';
        $assetUrl = 'https://assets.example.test';
        config(['app.asset_url' => $assetUrl]);

        $version = (new HandleInertiaRequests)->version(Request::create('/'));

        $this->assertSame(hash('xxh128', $assetUrl), $version);
    }
}
