<?php

return [
    'enabled' => env('AI_CHATBOT_ENABLED', false),
    'provider' => env('AI_CHATBOT_PROVIDER', 'openai'),
    'api_key' => env('AI_CHATBOT_API_KEY', ''),
    'model' => env('AI_CHATBOT_MODEL', 'gpt-4o-mini'),
    'temperature' => (float) env('AI_CHATBOT_TEMPERATURE', 0.7),
    'max_tokens' => (int) env('AI_CHATBOT_MAX_TOKENS', 500),
    'system_prompt' => env('AI_CHATBOT_SYSTEM_PROMPT', ''),
];
