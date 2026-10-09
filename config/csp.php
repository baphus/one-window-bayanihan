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
];
