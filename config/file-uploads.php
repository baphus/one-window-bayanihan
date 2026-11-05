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
    | Environment variable FILE_UPLOAD_MAX_SIZE sets the default max_size
    | for all contexts (20480 KB = 20 MB).
    |
    */

    // Top-level shorthand for simple byte-size checks in FormRequest rules
    'max_size' => (int) env('FILE_UPLOAD_MAX_SIZE', 20480),

    // Default config for StorageService::validate(). Every upload context
    // falls back to this when no context-specific block exists.
    'default' => [
        // PDF + images only; Office documents carry macro/embedded-payload risk.
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
        'max_size' => (int) env('FILE_UPLOAD_MAX_SIZE', 20480),
    ],
];
