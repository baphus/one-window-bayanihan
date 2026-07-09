<?php

use App\Http\Controllers\Api\CspViolationController;
use App\Http\Controllers\Api\PhilippineAddressController;
use Illuminate\Support\Facades\Route;

// Public address lookup endpoints (PSGC government data — no auth required)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/address/regions', [PhilippineAddressController::class, 'regions']);
    Route::get('/address/provinces', [PhilippineAddressController::class, 'provinces']);
    Route::get('/address/cities', [PhilippineAddressController::class, 'cities']);
    Route::get('/address/barangays', [PhilippineAddressController::class, 'barangays']);
    Route::get('/address/resolve', [PhilippineAddressController::class, 'resolve']);
});

// CSP violation reporting endpoint
Route::post('/csp/report', [CspViolationController::class, 'report'])
    ->middleware('throttle:120,1');
