<?php

// NOTE: AI chatbot-specific settings (provider, model, temperature, max_tokens, etc.)
// have been moved to config/ai-chatbot.php. The settings below are for non-chatbot
// AI features (Reports, Analytics insights).
return [
    'defaults' => [
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('AI_TEMPERATURE', 0.7),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 500),
    ],

    'providers' => [
        'gemini' => [
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        ],
    ],
];
