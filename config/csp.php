<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CSP Report URI
    |--------------------------------------------------------------------------
    |
    | Browsers POST CSP violation reports to this URI when enforcing the policy.
    | Set to an external CSP monitoring service endpoint (e.g. report-uri.io)
    | or leave empty to disable reporting. Both report-to and the legacy
    | report-uri fallback use this endpoint.
    |
    */
    'report_uri' => env('CSP_REPORT_URI', '/api/csp/report'),

    /*
    |--------------------------------------------------------------------------
    | Vite dev-server origin
    |--------------------------------------------------------------------------
    |
    | Read only by the development CSP in ContentSecurityPolicy, so the policy
    | can allow Vite's HMR socket and scripts. It lives here because env() must
    | not be called from application code: with a cached config (which the
    | production entrypoint always builds) env() returns null outside config.
    |
    */
    'vite_dev_server_url' => env('VITE_DEV_SERVER_URL', 'http://127.0.0.1:5173'),
];
