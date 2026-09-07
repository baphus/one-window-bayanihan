<?php

return [
    'enabled' => env('AI_CHATBOT_ENABLED', false),
    // Use a model that supports function tools and structured output.
    'provider' => env('AI_CHATBOT_PROVIDER', 'gemini'),
    'model' => env('AI_CHATBOT_MODEL', 'gemini-flash-latest'),
    'temperature' => (float) env('AI_CHATBOT_TEMPERATURE', 0.2),
    'max_tokens' => max(500, (int) env('AI_CHATBOT_MAX_TOKENS', 2000)),
    'timeout' => max(1, min(120, (int) env('AI_CHATBOT_TIMEOUT', 45))),
    'max_steps' => max(2, min(8, (int) env('AI_CHATBOT_MAX_STEPS', 6))),
    'max_tool_calls' => max(1, min(12, (int) env('AI_CHATBOT_MAX_TOOL_CALLS', 8))),
    'max_article_reads' => max(1, min(8, (int) env('AI_CHATBOT_MAX_ARTICLE_READS', 6))),
    'max_context_characters' => max(1000, min(48000, (int) env('AI_CHATBOT_MAX_CONTEXT_CHARACTERS', 24000))),
    'assistant_name' => env('APP_ASSISTANT_NAME', 'Bayani'),
];
