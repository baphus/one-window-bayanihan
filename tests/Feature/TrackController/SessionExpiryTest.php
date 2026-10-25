<?php

namespace Tests\Feature\TrackController;

use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    private string $trackerNumber = 'DMW-2026-000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * @param  array<string, mixed>  $binding
     */
    private function bindSession(array $binding = []): void
    {
        $this->withSession([
            TrackingService::SESSION_KEY => array_merge([
                'tracker_number' => $this->trackerNumber,
                'email' => 'ofw@example.com',
                'verified_at' => now()->toISOString(),
            ], $binding),
        ]);
    }

    /**
     * @param  array<string, mixed>  $binding
     */
    private function assertBindingValidity(bool $expected, array $binding): void
    {
        $session = $this->app['session']->driver();
        $session->put(TrackingService::SESSION_KEY, array_merge([
            'tracker_number' => $this->trackerNumber,
            'email' => 'ofw@example.com',
            'verified_at' => now()->toISOString(),
        ], $binding));

        $request = request();
        $request->setLaravelSession($session);

        $this->assertSame(
            $expected,
            app(TrackingService::class)->hasValidSessionBinding($request, $this->trackerNumber)
        );
    }

    public function test_fresh_verified_binding_is_valid(): void
    {
        $this->assertBindingValidity(true, []);
    }

    public function test_binding_older_than_thirty_minutes_is_invalid(): void
    {
        $this->assertBindingValidity(false, ['verified_at' => now()->subMinutes(31)->toISOString()]);
    }

    public function test_binding_without_verified_at_is_invalid(): void
    {
        $this->assertBindingValidity(false, ['verified_at' => null]);
    }

    public function test_expired_binding_cannot_view_tracking_page(): void
    {
        $this->bindSession(['verified_at' => now()->subMinutes(31)->toISOString()]);

        $this->get(route('track.show', ['tracker_number' => $this->trackerNumber]))
            ->assertSessionHasErrors('tracker_number');
    }

    public function test_logout_clears_tracking_session(): void
    {
        $this->bindSession();

        $this->post(route('track.logout'))
            ->assertRedirect(route('track.index'))
            ->assertSessionMissing(TrackingService::SESSION_KEY);
    }
}
