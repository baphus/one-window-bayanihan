<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        // Props should stay limited to what `Pages/Welcome.jsx` actually
        // consumes (plus the shared `auth`/`roles` from HandleInertiaRequests):
        // the page no longer branches on route existence.
        return Inertia::render('Welcome', [
            'agencies' => Agency::where('is_active', true)->get([
                'id', 'name', 'short', 'slug', 'logo_url',
                'map_link', 'latitude', 'longitude', 'location_query',
            ])->toArray(),
        ]);
    }
}
