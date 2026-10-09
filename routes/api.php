<?php

use App\Http\Controllers\Api\CspViolationController;
use App\Http\Controllers\Api\ReadinessController;
use App\Http\Controllers\Api\ResendWebhookController;
use Illuminate\Support\Facades\Route;

// Deep readiness probe for external monitoring — database, scheduler heartbeat,
// queue backlog. Requires the X-Monitoring-Token header and 404s when no token is
// configured. Deliberately NOT the container health check: the platform must keep
// probing the shallow /up route, or a database blip becomes a restart loop.
Route::get('/readyz', ReadinessController::class)
    ->middleware('throttle:readiness')
    ->name('monitoring.readyz');

// CSP violation reporting endpoint
Route::post('/csp/report', [CspViolationController::class, 'report'])
    ->middleware('throttle:csp-report');

// Resend delivery webhooks (bounces, complaints, deliveries).
// Authenticated by Svix signature inside the controller, not by session or
// token — see App\Services\Mail\SvixWebhookVerifier. Kept in api.php so it
// bypasses CSRF, sessions, and the MFA middleware that the web group appends.
Route::post('/webhooks/resend', ResendWebhookController::class)
    ->middleware('throttle:resend-webhook')
    ->name('webhooks.resend');
