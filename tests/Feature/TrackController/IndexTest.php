<?php

namespace Tests\Feature\TrackController;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_loads(): void
    {
        $response = $this->get(route('track.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tracking/Portal'));
    }

    public function test_no_auth_required(): void
    {
        $response = $this->get(route('track.index'));

        $response->assertOk();
    }

    public function test_no_errors_on_initial_load(): void
    {
        $response = $this->get(route('track.index'));

        $response->assertOk();
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('success');
        $response->assertSessionMissing('error');
    }
}
