<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Help Center Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the AI-powered Help Center chatbot with
    | retrieval-augmented generation (RAG) using pgvector.
    |
    */

    // API keys
    'api_key' => env('AI_CHATBOT_API_KEY', ''),
    'embedding_api_key' => env('AI_EMBEDDING_API_KEY', env('AI_CHATBOT_API_KEY', '')),

    // Embedding settings
    'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => 1536,

    // Chunking settings
    'max_tokens_per_chunk' => (int) env('AI_MAX_TOKENS_PER_CHUNK', 800),
    'chunk_overlap_tokens' => (int) env('AI_CHUNK_OVERLAP_TOKENS', 100),

    // Retrieval settings
    'retrieval_max_results' => (int) env('AI_RETRIEVAL_MAX_RESULTS', 5),
    'retrieval_min_score' => (float) env('AI_RETRIEVAL_MIN_SCORE', 0.3),

    // Rate limiting
    'rate_limit_per_minute' => (int) env('AI_CHAT_RATE_LIMIT', 30),

    // Prompt injection protection patterns
    'injection_patterns' => [
        'ignore previous instructions',
        'reveal system prompt',
        'you are now',
        'forget all previous',
        'new instructions',
        'ignore all instructions',
        'you must now',
        'forget everything',
        'override instructions',
        'disregard',
    ],

    // Fallback message when no documentation found
    'default_fallback_message' => env('AI_FALLBACK_MESSAGE',
        'I could not find documentation for that. Please try rephrasing your question or browse our Help Center.'),

    // Logging
    'logging' => [
        'enabled' => (bool) env('AI_LOGGING_ENABLED', true),
        'channel' => 'chatbot',
        'log_retrieval' => true,
        'log_tokens' => true,
        'log_unanswered' => true,
    ],
];
