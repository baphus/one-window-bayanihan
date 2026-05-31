<?php

use App\Http\Controllers\Api\ClientSelectController;
use App\Http\Controllers\Api\PhilippineAddressController;
use Illuminate\Support\Facades\Route;

// Public address lookup endpoints (PSGC government data — no auth required)
Route::get('/address/regions', [PhilippineAddressController::class, 'regions']);
Route::get('/address/provinces', [PhilippineAddressController::class, 'provinces']);
Route::get('/address/cities', [PhilippineAddressController::class, 'cities']);
Route::get('/address/barangays', [PhilippineAddressController::class, 'barangays']);

Route::middleware(['auth', 'verified'])->group(function () {
    // Client selection for case creation form
    Route::get('/clients', [ClientSelectController::class, 'search']);
    Route::get('/clients/{client}', [ClientSelectController::class, 'show']);
});
