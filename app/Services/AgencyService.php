<?php

namespace App\Services;

use App\Models\Agency;
use Illuminate\Support\Collection;

class AgencyService
{
    /**
     * Active agencies for the public home page (same payload as the
     * previous route closure: only the fields rendered by public cards).
     */
    public function activeForHome(): Collection
    {
        return Agency::where('is_active', true)->get([
            'id', 'name', 'short', 'slug', 'logo_url',
            'map_link', 'latitude', 'longitude', 'location_query',
        ]);
    }
}
