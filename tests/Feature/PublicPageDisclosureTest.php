<?php

namespace Tests\Feature;

use App\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPageDisclosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_only_sends_the_fields_used_by_public_cards(): void
    {
        $agency = Agency::factory()->create(['contact_info' => 'public-office@example.test']);
        Agency::factory()->create(['is_active' => false]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->missing('laravelVersion')->missing('phpVersion')
            ->has('agencies', 1)
            ->where('agencies.0.id', $agency->id)
            ->where('agencies.0.name', $agency->name)
            ->where('agencies.0.slug', $agency->slug)
            ->has('agencies.0.logo_url')->has('agencies.0.map_link')
            ->has('agencies.0.latitude')->has('agencies.0.longitude')
            ->has('agencies.0.location_query')
            ->missing('agencies.0.contact_info')->missing('agencies.0.created_at')
            ->missing('agencies.0.deleted_by')
        );

        // The dedicated agency page must still show its public contact details.
        $this->get(route('partners.show', $agency->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PublicAgencies/Show')
                ->where('agency.contact_info', 'public-office@example.test')
                ->missing('laravelVersion')->missing('phpVersion')
            );
    }
}
