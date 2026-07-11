<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Generic S3-compatible object storage (default for file uploads).
         * Backward-compatible: falls back to SUPABASE_S3_* env vars when
         * the generic STORAGE_* vars are not set.
         */
        'object-storage' => [
            'driver' => env('STORAGE_DRIVER', env('SUPABASE_S3_DRIVER', 's3')),
            'key' => env('STORAGE_ACCESS_KEY', env('SUPABASE_S3_ACCESS_KEY')),
            'secret' => env('STORAGE_SECRET_KEY', env('SUPABASE_S3_SECRET_KEY')),
            'region' => env('STORAGE_REGION', env('SUPABASE_S3_REGION', 'ap-southeast-1')),
            'bucket' => env('STORAGE_BUCKET', env('SUPABASE_S3_BUCKET', 'case-files')),
            'endpoint' => env('STORAGE_ENDPOINT', env('SUPABASE_S3_ENDPOINT')),
            'root' => env('STORAGE_ROOT', env('SUPABASE_S3_ROOT', storage_path('app/storage'))),
            'url' => env('STORAGE_URL', env('SUPABASE_S3_URL')),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Legacy supabase-specific disk alias. References the same
         * object-storage configuration. Kept for backward compatibility.
         */
        'supabase' => [
            'driver' => env('SUPABASE_S3_DRIVER', 's3'),
            'key' => env('SUPABASE_S3_ACCESS_KEY'),
            'secret' => env('SUPABASE_S3_SECRET_KEY'),
            'region' => env('SUPABASE_S3_REGION', 'ap-southeast-1'),
            'bucket' => env('SUPABASE_S3_BUCKET', 'case-files'),
            'endpoint' => env('SUPABASE_S3_ENDPOINT'),
            'root' => env('SUPABASE_S3_ROOT', storage_path('app/supabase')),
            'url' => env('SUPABASE_S3_URL', '/supabase-storage'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Immutable audit log archive bundles (audit:archive / audit:prune).
         * Production must point this at S3-compatible object storage via
         * AUDIT_ARCHIVE_DRIVER=s3 (+ AUDIT_ARCHIVE_* or STORAGE_* credentials);
         * local/testing default to an on-disk root.
         */
        'audit-archives' => [
            'driver' => env('AUDIT_ARCHIVE_DRIVER', 'local'),
            'root' => env('AUDIT_ARCHIVE_DRIVER', 'local') === 'local'
                ? storage_path('app/audit-archives')
                : env('AUDIT_ARCHIVE_ROOT', 'audit-archives'),
            'key' => env('AUDIT_ARCHIVE_ACCESS_KEY', env('STORAGE_ACCESS_KEY')),
            'secret' => env('AUDIT_ARCHIVE_SECRET_KEY', env('STORAGE_SECRET_KEY')),
            'region' => env('AUDIT_ARCHIVE_REGION', env('STORAGE_REGION', 'ap-southeast-1')),
            'bucket' => env('AUDIT_ARCHIVE_BUCKET', env('STORAGE_BUCKET')),
            'endpoint' => env('AUDIT_ARCHIVE_ENDPOINT', env('STORAGE_ENDPOINT')),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
