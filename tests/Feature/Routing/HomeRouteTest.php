<?php

namespace Tests\Feature\Routing;

use App\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_returns_agencies_prop(): void
    {
        $agency = Agency::factory()->create(['is_active' => true]);
        Agency::factory()->create(['is_active' => false]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('agencies', 1)
            ->where('agencies.0.id', $agency->id)
            ->where('agencies.0.name', $agency->name)
        );
    }
}
