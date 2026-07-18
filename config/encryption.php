<?php

return [
    'key_id' => env('APP_KEY_ID'),
    'previous_key_ids' => array_values(array_filter(explode(',', (string) env('APP_PREVIOUS_KEY_IDS', '')))),
];
