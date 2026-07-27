<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Readiness endpoint token
    |--------------------------------------------------------------------------
    |
    | Shared secret required by GET /api/readyz. When this is empty the route
    | responds 404 rather than serving unauthenticated internals — fail closed,
    | so forgetting to set it cannot silently expose queue depth and backlog
    | counts to the internet.
    |
    | This is NOT the platform health check. Container health checks must keep
    | using the shallow /up endpoint: a deep check wired to the orchestrator
    | turns a transient database blip into a container restart loop.
    |
    */

    'readiness_token' => env('MONITORING_READINESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Readiness thresholds
    |--------------------------------------------------------------------------
    |
    | Crossing any of these makes /api/readyz respond 503 so an external monitor
    | can alert. They exist because /up answers 200 without touching the
    | database: during the staging bring-up it reported healthy continuously
    | while the queue worker and scheduler could not connect at all, and every
    | authenticated page returned 502.
    |
    */

    'thresholds' => [
        // Pending rows in `jobs`. A persistently rising backlog means the
        // worker is not consuming, which is invisible to /up.
        'queue_backlog' => (int) env('MONITORING_MAX_QUEUE_BACKLOG', 100),

        // Rows in `failed_jobs`. Non-zero is not automatically an outage, so
        // this is deliberately a threshold rather than a zero check.
        'failed_jobs' => (int) env('MONITORING_MAX_FAILED_JOBS', 25),

        // Seconds since the scheduler last wrote its heartbeat. schedule:work
        // ticks every minute, so 300s is five missed ticks.
        'scheduler_stale_seconds' => (int) env('MONITORING_SCHEDULER_STALE_SECONDS', 300),
    ],

];
