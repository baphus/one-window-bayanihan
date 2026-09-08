<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Served region scope
    |--------------------------------------------------------------------------
    |
    | PSGC region codes the address dropdowns may offer. The DMW VII office
    | currently serves only Central Visayas and the Negros Island Region (NIR).
    |
    | Change this list (e.g. add more region codes) to widen coverage without
    | touching code. An empty array — `served_regions => []` — disables the
    | restriction entirely and lists every Philippine region, which is the
    | natural starting point if the program ever goes international and the
    | region constraint drops out of the intake requirements.
    |
    */
    'served_regions' => [
        '0700000000', // Region VII (Central Visayas)
        '1800000000', // Negros Island Region (NIR, "Region 77" in some datasets)
    ],

];
