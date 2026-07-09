<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CSP Report URI
    |--------------------------------------------------------------------------
    |
    | Browser POSTs CSP violation reports to this URI in Report-Only mode.
    | Set to an external CSP monitoring service endpoint (e.g. report-uri.io)
    | or leave empty to omit the report-uri directive.
    |
    */
    'report_uri' => env('CSP_REPORT_URI', ''),
];
