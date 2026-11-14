<?php

namespace Tests\Feature\Middleware;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shares_only_account_fields_for_ofw_and_preserves_staff_profile_fields(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::OFW->value,
            'department' => 'Private staff-only field',
            'bio' => 'Private staff-only biography',
        ]);

        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $user);
        $shared = (new HandleInertiaRequests)->share($request);
        $this->assertSame($user->email, $shared['auth']['user']['email']);
        foreach (['client', 'type', 'department', 'position', 'office_location', 'bio', 'emergency_contact', 'timezone', 'profile_completed_at'] as $field) {
            $this->assertArrayNotHasKey($field, $shared['auth']['user']);
        }

        $staff = User::factory()->create(['role' => UserRole::CASE_MANAGER->value, 'department' => 'Casework']);
        $request->setUserResolver(fn () => $staff);
        $this->assertSame('Casework', (new HandleInertiaRequests)->share($request)['auth']['user']['department']);

        $guestRequest = Request::create('/');
        $guestRequest->setLaravelSession(app('session')->driver());
        $guest = (new HandleInertiaRequests)->share($guestRequest);
        $this->assertNull($guest['auth']['user']);
    }

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
