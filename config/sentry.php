<?php

use Sentry\Tracing\SamplingContext;

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Capture release version from git
    'release' => trim(env('SENTRY_RELEASE') ?: (function_exists('exec') ? @exec('git log --pretty="%h" -n1 HEAD') : '')),

    // Capture environment
    'environment' => env('APP_ENV'),

    // Sample rate for error traces (1.0 = 100% of transactions)
    'traces_sample_rate' => (float) env('SENTRY_LARAVEL_TRACES_SAMPLE_RATE', 0.2),

    // Controls which paths should be traced
    'traces_sampler' => function (SamplingContext $context): float {
        // Always trace Inertia page loads
        return 0.2;
    },

    // Breadcrumb configuration
    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => true,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
    ],

];
