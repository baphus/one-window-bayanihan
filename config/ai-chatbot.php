<?php

return [
    'enabled' => env('AI_CHATBOT_ENABLED', false),
    'provider' => env('AI_CHATBOT_PROVIDER', 'ollama'),
    // Local provider — Ollama runs on localhost:11434 by default (config/ai.php).
    'model' => env('AI_CHATBOT_MODEL', 'llama3:latest'),
    'temperature' => (float) env('AI_CHATBOT_TEMPERATURE', 0.7),
    'max_tokens' => (int) env('AI_CHATBOT_MAX_TOKENS', 500),
    'system_prompt' => env('AI_CHATBOT_SYSTEM_PROMPT', ''),
    'assistant_name' => env('APP_ASSISTANT_NAME', 'Bayani'),
];
