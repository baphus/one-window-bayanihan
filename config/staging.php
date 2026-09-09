<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staging seeder allowed environments
    |--------------------------------------------------------------------------
    | StagingSeeder truncates business tables before re-seeding and must never
    | run in production. Override with a comma-separated list:
    | STAGING_SEEDER_ALLOWED_ENVS=staging,local
    */
    'seeder_allowed_envs' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('STAGING_SEEDER_ALLOWED_ENVS', 'staging,local'))
    ))) ?: ['staging', 'local'],

];
