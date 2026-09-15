<?php

namespace App\Http\Controllers;

use App\Services\AgencyService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(AgencyService $agencies)
    {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'agencies' => $agencies->activeForHome()->toArray(),
        ]);
    }
}
