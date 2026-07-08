<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default file upload validation rules
    |--------------------------------------------------------------------------
    | Each context defines the allowed MIME types and maximum file size in KB.
    | Reference these via config('file-uploads.<context>') in StorageService.
    */

    'default' => [
        'mimes' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
        'max_size' => (int) env('FILE_UPLOAD_MAX_SIZE', 10240), // KB
    ],

    'case_document' => [
        'mimes' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
        'max_size' => (int) env('FILE_UPLOAD_MAX_SIZE', 10240),
    ],

    'referral_attachment' => [
        'mimes' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
        'max_size' => (int) env('FILE_UPLOAD_MAX_SIZE', 10240),
    ],
];
