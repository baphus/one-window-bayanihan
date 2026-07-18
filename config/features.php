<?php

return [
    'case_drafts' => [
        'enabled' => filter_var(env('FEATURE_CASE_DRAFTS', false), FILTER_VALIDATE_BOOL),
    ],
];
