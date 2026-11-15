<?php

return [
    'enabled' => env('AI_CHATBOT_ENABLED', false),
    // Use a model that supports function tools and structured output.
    'provider' => env('AI_CHATBOT_PROVIDER', 'gemini'),
    'model' => env('AI_CHATBOT_MODEL', 'gemini-3.5-flash-lite'),
    'temperature' => (float) env('AI_CHATBOT_TEMPERATURE', 0.2),
    'max_tokens' => max(350, min(350, (int) env('AI_CHATBOT_MAX_TOKENS', 350))),
    'timeout' => max(1, min(120, (int) env('AI_CHATBOT_TIMEOUT', 45))),
    'max_steps' => max(2, min(8, (int) env('AI_CHATBOT_MAX_STEPS', 6))),
    'max_tool_calls' => min(4, max(1, (int) env('AI_CHATBOT_MAX_TOOL_CALLS', 4))),
    'max_article_reads' => min(2, max(1, (int) env('AI_CHATBOT_MAX_ARTICLE_READS', 2))),
    'max_context_characters' => min(12000, max(1000, (int) env('AI_CHATBOT_MAX_CONTEXT_CHARACTERS', 12000))),
    'assistant_name' => env('APP_ASSISTANT_NAME', 'Bayani'),
];
