<?php

use App\Http\Controllers\Api\ClientSelectController;
use App\Http\Controllers\Api\PhilippineAddressController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Philippine address lookup endpoints (cached PSGC Cloud API proxy)
    Route::get('/address/regions', [PhilippineAddressController::class, 'regions']);
    Route::get('/address/provinces', [PhilippineAddressController::class, 'provinces']);
    Route::get('/address/cities', [PhilippineAddressController::class, 'cities']);
    Route::get('/address/barangays', [PhilippineAddressController::class, 'barangays']);

    // Client selection for case creation form
    Route::get('/clients', [ClientSelectController::class, 'search']);
    Route::get('/clients/{client}', [ClientSelectController::class, 'show']);
});
