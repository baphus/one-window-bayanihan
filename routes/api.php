<?php

use App\Http\Controllers\Api\CspViolationController;
use App\Http\Controllers\Api\PhilippineAddressController;
use App\Http\Controllers\Api\ReadinessController;
use App\Http\Controllers\Api\ResendWebhookController;
use Illuminate\Support\Facades\Route;

// Deep readiness probe for external monitoring — database, scheduler heartbeat,
// queue backlog. Requires the X-Monitoring-Token header and 404s when no token is
// configured. Deliberately NOT the container health check: the platform must keep
// probing the shallow /up route, or a database blip becomes a restart loop.
Route::get('/readyz', ReadinessController::class)
    ->middleware('throttle:60,1')
    ->name('monitoring.readyz');

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

// Resend delivery webhooks (bounces, complaints, deliveries).
// Authenticated by Svix signature inside the controller, not by session or
// token — see App\Services\Mail\SvixWebhookVerifier. Kept in api.php so it
// bypasses CSRF, sessions, and the MFA middleware that the web group appends.
Route::post('/webhooks/resend', ResendWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('webhooks.resend');
