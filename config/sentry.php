<?php

use Sentry\Event;

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Capture release version from git
    'release' => trim(env('SENTRY_RELEASE') ?: (function_exists('exec') ? @exec('git log --pretty="%h" -n1 HEAD') : '')),

    // Capture environment
    'environment' => env('APP_ENV'),

    // Sample rate for error traces (1.0 = 100% of transactions)
    //
    // There was also a 'traces_sampler' closure here. A closure in a config file
    // cannot be serialized, so `php artisan config:cache` aborted with
    //   Your configuration files could not be serialized because the value at
    //   "sentry.traces_sampler" is non-serializable
    // on every boot. The entrypoint used to swallow that with `|| true`, so the
    // application silently ran with UNCACHED config in every environment.
    //
    // Removing it changes no behaviour: the closure ignored its SamplingContext
    // and returned a flat 0.2, which is precisely what traces_sample_rate below
    // already does. Reintroducing per-path sampling means an invokable class
    // referenced by name — a class-string serializes, a closure does not.
    'traces_sample_rate' => (float) env('SENTRY_LARAVEL_TRACES_SAMPLE_RATE', 0.2),

    // Breadcrumb configuration
    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
    ],

    // Never send cookies, authorization headers, or PII to Sentry.
    'send_default_pii' => false,

    // Scrub sensitive data before events leave the SDK.
    'before_send' => function (Event $event): ?Event {
        // Strip query strings from request URLs (may contain tokens).
        $request = $event->getRequest();
        if (! empty($request['url'])) {
            $request['url'] = parse_url($request['url'], PHP_URL_PATH);
        }

        // Remove sensitive headers.
        if (! empty($request['headers'])) {
            unset(
                $request['headers']['authorization'],
                $request['headers']['cookie'],
                $request['headers']['set-cookie']
            );
        }

        $event->setRequest($request);

        return $event;
    },

];
