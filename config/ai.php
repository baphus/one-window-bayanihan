<?php

return [
    'defaults' => [
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('AI_TEMPERATURE', 0.7),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 500),
    ],
];
