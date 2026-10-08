<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'resend' => [
        'key' => env('RESEND_API_KEY'),

        // Svix signing secret ("whsec_...") for the delivery webhook endpoint.
        // The endpoint fails closed when this is unset.
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    ],

    'psgc' => [
        'api_base' => env('PSGC_API_BASE', 'https://psgc.cloud/api'),
    ],

    'cloudinary' => [
        'url' => env('CLOUDINARY_URL'),
    ],

    'malware' => [
        'scanner' => env('MALWARE_SCANNER', 'null'),
        // When true, an unreachable ClamAV daemon (or unexpected response)
        // logs a warning and treats the file as clean so uploads keep working.
        // Set to false in environments where unscanned uploads must be blocked.
        'fail_open' => env('MALWARE_FAIL_OPEN', true),
    ],

];
