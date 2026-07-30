<?php

return [
    'enabled' => env('AI_CHATBOT_ENABLED', false),
    // Hosted provider (default: OpenRouter free tier — needs OPENROUTER_API_KEY).
    // Any provider in config/ai.php works, including local Ollama/llama.cpp.
    // Retrieval carries relevance; the model only rephrases grounded content,
    // so small/free models are adequate.
    'provider' => env('AI_CHATBOT_PROVIDER', 'openrouter'),
    'model' => env('AI_CHATBOT_MODEL', 'openai/gpt-oss-120b:free'),
    'temperature' => (float) env('AI_CHATBOT_TEMPERATURE', 0.7),
    'max_tokens' => (int) env('AI_CHATBOT_MAX_TOKENS', 500),
    'timeout' => (int) env('AI_CHATBOT_TIMEOUT', 180),
    'system_prompt' => env('AI_CHATBOT_SYSTEM_PROMPT', ''),
    'assistant_name' => env('APP_ASSISTANT_NAME', 'Bayani'),

    /*
    |--------------------------------------------------------------------------
    | Embedding (pgvector — dense vector retrieval)
    |--------------------------------------------------------------------------
    |
    | Converts text into dense vectors for semantic search via pgvector.
    | The embedding model is independently configurable from the generation
    | model, so retrieval and generation can be swapped separately.
    |
    | Rebuild embeddings with `php artisan chatbot:index`.
    |
    */
    'embedding' => [
        // Embedding provider — 'ollama' (local dev) or 'api' (hosted).
        'provider' => env('AI_CHATBOT_EMBEDDING_PROVIDER', 'ollama'),

        // API base URL for embedding requests.
        // Ollama: http://localhost:11434/api/embed
        // Hosted: https://api.openai.com/v1/embeddings (or any OpenAI-compatible)
        'url' => env('AI_CHATBOT_EMBEDDING_URL', 'http://localhost:11434/api/embed'),

        // API key for hosted embedding providers (unused by Ollama).
        'api_key' => env('AI_CHATBOT_EMBEDDING_API_KEY'),

        // Model name — nomic-embed-text (Ollama) or text-embedding-3-small (OpenAI), etc.
        'model' => env('AI_CHATBOT_EMBEDDING_MODEL', 'nomic-embed-text'),

        // Vector dimensions — must match the model's output size
        'dimensions' => (int) env('AI_CHATBOT_EMBEDDING_DIMENSIONS', 768),

        // HNSW index parameters (tune for speed vs accuracy trade-off)
        'hnsw_m' => (int) env('AI_CHATBOT_EMBEDDING_HNSW_M', 16),
        'hnsw_ef_construction' => (int) env('AI_CHATBOT_EMBEDDING_HNSW_EF', 64),
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence thresholds
    |--------------------------------------------------------------------------
    |
    | Controls how the chatbot decides between LLM response and fallback
    | paths based on the vector similarity confidence score (0.0–1.0).
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Vector Database backend
    |--------------------------------------------------------------------------
    |
    | Which vector-store adapter to use for semantic search.
    | Supported: 'pgvector' (default), 'pinecone' (future).
    |
    */
    'vector_db' => [
        // Supported: 'pgvector' (default), 'pinecone' (future).
        'driver' => env('VECTOR_DB_DRIVER', 'pgvector'),

        // Pinecone configuration (used when driver is 'pinecone')
        'pinecone' => [
            'api_key' => env('PINECONE_API_KEY'),
            'environment' => env('PINECONE_ENVIRONMENT'),
            'index' => env('PINECONE_INDEX', 'bayanihan'),
        ],
    ],

    'hybrid' => [
        // Confidence >= this → LLM-generated response from retrieved content
        'llm_confidence' => (float) env('AI_CHATBOT_LLM_CONFIDENCE', 0.3),

        // Below llm_confidence → contextual retry or fallback
    ],
];
