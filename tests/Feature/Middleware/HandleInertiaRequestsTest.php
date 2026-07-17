<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    #[Test]
    public function it_returns_an_empty_asset_version_in_local_environment(): void
    {
        $this->app['env'] = 'local';
        config(['app.asset_url' => 'https://assets.example.test']);

        $version = (new HandleInertiaRequests)->version(Request::create('/'));

        $this->assertSame('', $version);
    }

    #[Test]
    public function it_does_not_trigger_a_version_change_for_an_empty_local_version(): void
    {
        $this->app['env'] = 'local';

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => '',
        ]);
        $middleware = new class extends HandleInertiaRequests
        {
            public function share(Request $request): array
            {
                return [];
            }

            public function onVersionChange(Request $request, Response $response): Response
            {
                return new Response('version changed', 409);
            }
        };

        $response = $middleware->handle(
            $request,
            fn () => new Response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Inertia-Location'));
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
