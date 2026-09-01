<?php

namespace App\Support;

use Sentry\Event;

/**
 * Scrubs sensitive data from Sentry events before they leave the SDK.
 *
 * Exposed as a static method referenced by a callable array
 * `[SentryBeforeSend::class, 'scrub']` in config/sentry.php. A callable array of
 * class-string + method name is serializable, so `php artisan config:cache`
 * succeeds — unlike a closure, which made every production boot fail to cache
 * config and silently run with UNCACHED config.
 */
class SentryBeforeSend
{
    public static function scrub(Event $event): ?Event
    {
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
    }
}
