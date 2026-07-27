<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Operating Timezone
    |--------------------------------------------------------------------------
    |
    | The jurisdiction this system operates in. Storage and internal timestamps
    | stay UTC above, which is correct; this is for values that must follow the
    | local calendar rather than UTC.
    |
    | Case numbers are the motivating case. OWB-{YEAR}-{NNNNN} derived its year
    | from now()->format('Y') under UTC, so the year rolled over at 08:00 PHT on
    | 1 January and cases filed in Manila between midnight and 08:00 were stamped
    | with the previous year — a records-retention defect, since the reference is
    | expected to follow the jurisdiction's calendar year.
    |
    | 'Asia/Manila' was already hardcoded in AuditLogFormatter, in
    | ReportsExportService::TZ, and as the users.timezone column default. This is
    | the single source of truth those should converge on.
    |
    */

    'operating_timezone' => env('APP_OPERATING_TIMEZONE', 'Asia/Manila'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trash Retention Period
    |--------------------------------------------------------------------------
    |
    | Number of days soft-deleted cases are retained in the trash before the
    | scheduled auto-purge permanently removes them. Override per deployment
    | via APP_TRASH_RETENTION_DAYS in .env.
    |
    */
    'trash_retention_days' => (int) env('APP_TRASH_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Search Engine Indexing
    |--------------------------------------------------------------------------
    |
    | When false, every response carries "X-Robots-Tag: noindex, nofollow".
    |
    | The default keeps non-production environments out of search results, which
    | matters once they have a guessable hostname. But a production environment
    | that is provisioned and not yet launched should also stay unindexed —
    | otherwise a search engine can index a half-configured public service, and
    | removing those results afterwards is slow and incomplete.
    |
    | So this is deliberately a separate switch from APP_ENV: set
    | SEARCH_INDEXING_ENABLED=true as an explicit go-live step, not as a side
    | effect of setting APP_ENV=production.
    |
    */
    'search_indexing_enabled' => filter_var(
        env('SEARCH_INDEXING_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Link Preview Metadata
    |--------------------------------------------------------------------------
    |
    | Used for the description meta tag and the Open Graph / Twitter card that
    | messaging apps, search engines, and social platforms render when someone
    | pastes a link to this service.
    |
    | Kept in config rather than hardcoded in the template so the wording can be
    | changed per deployment without a code edit, and so tests can assert it.
    |
    */
    'description' => env(
        'APP_DESCRIPTION',
        'Official case management service of the Department of Migrant Workers '
        .'Region VII. File and track assistance requests for Overseas Filipino '
        .'Workers and their families.'
    ),

    'owner' => env('APP_OWNER', 'Department of Migrant Workers - Region VII'),

    // Absolute or root-relative path to the 1200x630 social preview image.
    // Must be a raster format: SVG is not reliably supported by Facebook,
    // Twitter/X, or LinkedIn unfurlers.
    'social_image' => env('APP_SOCIAL_IMAGE', '/og-image.png'),

    // Brand colour used for the browser/OS UI chrome on mobile.
    'theme_color' => env('APP_THEME_COLOR', '#005288'),

];
