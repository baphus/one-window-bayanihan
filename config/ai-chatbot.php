<?php

return [
    'enabled' => env('AI_CHATBOT_ENABLED', false),
    'provider' => env('AI_CHATBOT_PROVIDER', 'ollama'),
    // Local provider — Ollama runs on localhost:11434 by default (config/ai.php).
    // llama3.2:3b (~2 GB) is CPU-friendly; retrieval carries relevance, the model
    // only rephrases grounded content. Raise to a larger model on stronger hardware.
    'model' => env('AI_CHATBOT_MODEL', 'llama3.2:3b'),
    'temperature' => (float) env('AI_CHATBOT_TEMPERATURE', 0.7),
    'max_tokens' => (int) env('AI_CHATBOT_MAX_TOKENS', 500),
    'system_prompt' => env('AI_CHATBOT_SYSTEM_PROMPT', ''),
    'assistant_name' => env('APP_ASSISTANT_NAME', 'Bayani'),

    /*
    |--------------------------------------------------------------------------
    | Lexical retrieval (SQLite FTS5 — no vector database)
    |--------------------------------------------------------------------------
    |
    | Helpdesk sections and guide topics are indexed into a standalone SQLite
    | FTS5 file and ranked with BM25. Scores below are on the negated-BM25
    | scale (higher = more relevant). Rebuild with `php artisan chatbot:index`.
    |
    */
    'retrieval' => [
        'index_path' => env('AI_CHATBOT_INDEX_PATH', storage_path('app/chatbot-index.sqlite')),
        'max_results' => 3,

        // Hits scoring below this are treated as "no match" (fallback path).
        'min_score' => (float) env('AI_CHATBOT_MIN_SCORE', 0.4),

        // Verbatim tier: answer with the section content directly (no LLM call)
        // when the top hit scores at least verbatim_min_score AND outranks the
        // best hit from a DIFFERENT source by verbatim_gap_ratio.
        //
        // Tuned 2026-07 against fixture queries (see openspec change
        // simplify-chatbot-pipeline, task 3.7): unambiguous single-topic queries
        // ("how does OTP verification work" 10.96 vs 3.39, "contact number of
        // OWWA" 8.91 vs 4.70, "I lost my tracker number" 12.28 vs 7.08) clear
        // both bars; ambiguous ones ("why is my OTP not arriving" 6.43 vs 6.24,
        // "what do the colors mean" 5.63 vs 4.84) fall through to the LLM.
        'verbatim_min_score' => (float) env('AI_CHATBOT_VERBATIM_MIN_SCORE', 6.0),
        'verbatim_gap_ratio' => (float) env('AI_CHATBOT_VERBATIM_GAP_RATIO', 1.5),

        /*
        | Domain synonym map, applied to query tokens before the FTS match.
        | Keys are single lowercase tokens as typed by users; values are lists
        | of words/phrases that also exist in the content. Support staff can
        | extend this without code changes.
        */
        'synonyms' => [
            // Overseas-employment domain terms
            'oec' => ['overseas employment certificate', 'exit clearance'],
            'balik' => ['returning', 'return'],
            'manggagawa' => ['worker', 'ofw'],
            'abroad' => ['overseas', 'ofw'],

            // Filipino → content vocabulary
            'trabaho' => ['work', 'employment', 'job'],
            'tulong' => ['help', 'assistance', 'support'],
            'ahensya' => ['agency'],
            'reklamo' => ['complaint', 'case'],
            'sundan' => ['track', 'follow'],
            'estado' => ['status'],
            'kaso' => ['case'],
            'dokumento' => ['document', 'requirements'],

            // Colloquial phrasings → content vocabulary
            'follow' => ['track', 'status'],
            'followup' => ['track', 'status'],
            'update' => ['status', 'track'],
            'check' => ['track', 'status', 'view'],
            'complaint' => ['case', 'issue'],
            'passcode' => ['otp', 'code'],
            'pin' => ['otp', 'code'],
            'hotline' => ['contact', 'phone'],
        ],
    ],
];
