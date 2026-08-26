<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CSP Report URI
    |--------------------------------------------------------------------------
    |
    | Browsers POST CSP violation reports to this URI when enforcing the policy.
    | Set to an external CSP monitoring service endpoint (e.g. report-uri.io)
    | or leave empty to omit the report-uri directive.
    |
    */
    'report_uri' => env('CSP_REPORT_URI', '/csp/report'),
];
