<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'agencies' => Agency::where('is_active', true)->get([
                'id', 'name', 'short', 'slug', 'logo_url',
                'map_link', 'latitude', 'longitude', 'location_query',
            ])->toArray(),
        ]);
    }
}
