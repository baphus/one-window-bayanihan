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
            // Path-style suits MinIO and Supabase S3. Amazon Lightsail buckets are
            // addressed virtual-hosted style, so this must be switchable rather
            // than hardcoded. Default stays true to preserve existing behaviour.
            'use_path_style_endpoint' => (bool) env('STORAGE_USE_PATH_STYLE', true),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Cloudflare R2 object storage.
         * S3-compatible but requires path-style endpoints and a dedicated
         * endpoint URL per account. Switch by setting FILESYSTEM_DISK=r2
         * and filling in the R2_* env vars below.
         */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'url' => env('R2_PUBLIC_URL'),
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
         * Inherits from the active FILESYSTEM_DISK unless explicitly overridden
         * via AUDIT_ARCHIVE_* env vars. When FILESYSTEM_DISK is r2 or
         * object-storage, audit archives use the same credentials automatically.
         * local/testing default to an on-disk root.
         */
        'audit-archives' => [
            'driver' => env('AUDIT_ARCHIVE_DRIVER', in_array(env('FILESYSTEM_DISK', 'local'), ['r2', 'object-storage', 'supabase']) ? 's3' : 'local'),
            'root' => env('AUDIT_ARCHIVE_ROOT', 'audit-archives'),
            'key' => env('AUDIT_ARCHIVE_ACCESS_KEY', env('STORAGE_ACCESS_KEY', env('R2_ACCESS_KEY_ID'))),
            'secret' => env('AUDIT_ARCHIVE_SECRET_KEY', env('STORAGE_SECRET_KEY', env('R2_SECRET_ACCESS_KEY'))),
            'region' => env('AUDIT_ARCHIVE_REGION', env('STORAGE_REGION', env('R2_REGION', 'auto'))),
            'bucket' => env('AUDIT_ARCHIVE_BUCKET', env('STORAGE_BUCKET', env('R2_BUCKET'))),
            'endpoint' => env('AUDIT_ARCHIVE_ENDPOINT', env('STORAGE_ENDPOINT', env('R2_ENDPOINT'))),
            'use_path_style_endpoint' => (bool) env('AUDIT_ARCHIVE_USE_PATH_STYLE', env('STORAGE_USE_PATH_STYLE', true)),
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
