<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hot retention window (days)
    |--------------------------------------------------------------------------
    | Audit entries younger than this stay queryable in Postgres. Older
    | entries are archived to immutable bundles by audit:archive and then
    | removed by audit:prune (which refuses rows not yet archived).
    */
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Archive storage
    |--------------------------------------------------------------------------
    | Filesystem disk receiving monthly NDJSON bundles + manifests.
    | Production must configure this disk as S3-compatible object storage
    | (see config/filesystems.php 'audit-archives').
    */
    'archive_disk' => env('AUDIT_ARCHIVE_DISK', 'audit-archives'),

    /*
    |--------------------------------------------------------------------------
    | Chain verification baseline
    |--------------------------------------------------------------------------
    | Rows written before the advisory-lock chain fix deployed can contain
    | forks from the historical race condition. Chain breaks at rows older
    | than this timestamp are reported as accepted anomalies instead of
    | failing audit:verify. Set to the deployment timestamp of the fix;
    | leave null for strict verification of the entire table.
    */
    'chain_verified_from' => env('AUDIT_CHAIN_VERIFIED_FROM'),

    /*
    |--------------------------------------------------------------------------
    | Export limits
    |--------------------------------------------------------------------------
    | The audit CSV export requires an explicit date range: the UI defaults
    | to `default_days`, the range may not exceed the hot retention window,
    | and result sets above `max_rows` are rejected before streaming.
    */
    'export' => [
        'default_days' => 30,
        'max_rows' => (int) env('AUDIT_EXPORT_MAX_ROWS', 100000),
    ],

];
