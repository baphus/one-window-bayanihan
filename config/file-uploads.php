<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Each context defines allowed file MIME types and a max size in KB.
    | The StorageService::validate() method reads config('file-uploads.<context>')
    | to drive server-side validation.
    |
    | Every newly uploaded file is limited to 5 MiB (5120 KB).
    |
    */

    // Top-level shorthand for simple byte-size checks in FormRequest rules
    'max_size' => 5120,

    // Default config for StorageService::validate(). Every upload context
    // falls back to this when no context-specific block exists.
    'default' => [
        // PDF + images only; Office documents carry macro/embedded-payload risk.
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
        'max_size' => 5120,
    ],
];
