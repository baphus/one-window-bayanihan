## Context

The chatbot (`ChatbotController::message`) currently runs a 5-stage pipeline where 3 of the 5 stages call the LLM: intent classification (1 call), section picking (up to 3 calls, one per matched article), and answer generation (1 call). The provider is Ollama with `llama3:latest` (8B). Retrieval is a hand-rolled `str_contains` keyword scorer over article titles/excerpts only (`matchArticles`), and `ChatbotHelpdeskService` regex-parses TypeScript content files (`resources/js/data/helpdesk/content/*.ts`, 32 articles) on every request with only per-request in-memory caching. `ChatbotDataService` and `ChatbotCaseService` are never injected into the controller — dead code with tests.

Target deployment: one Docker host, CPU-only, minimal RAM. The current design produces 30s–2min responses there.

Constraints:
- No vector database, embedding models, or external search services.
- Frontend `ChatBot.jsx` API contract must not change (`POST /chatbot/message` → `{reply, actions?, error?}`).
- Security controls must be preserved: prompt-injection guard, role-scoped audience filtering, no PII in prompts (ISO 27001/DPTM readiness — built in, not retrofitted).

## Goals / Non-Goals

**Goals:**
- ≤1 LLM call per message; 0 for greetings/identity/gibberish and high-confidence verbatim answers.
- Sub-second responses for non-LLM paths; a few seconds for LLM paths on a 3B model on CPU.
- Better retrieval recall (body-text search, BM25, synonyms) with zero new services.
- Chatbot stays useful (verbatim mode) when the model backend is down.
- Remove dead code and stale architecture docs.

**Non-Goals:**
- No vector/semantic search, no embeddings, no reranking models.
- No live case-data lookups in chat (the dead `ChatbotCaseService` OTP flow is removed, not revived).
- No streaming responses, no frontend redesign.
- No change to helpdesk content authoring format.

## Decisions

### D1: Heuristic intent detection replaces the classification LLM call
Regex/keyword rules in a new `ChatbotIntentService`:
- `greeting`: message matches a greeting lexicon (EN + Filipino) and has ≤4 meaningful tokens.
- `identity`: matches "who/what are you", "what can you do" patterns.
- `gibberish`: zero meaningful tokens after the existing stop-word filter, or repeated-character/keyboard-mash heuristics, or bare clarification requests ("what?", "say that again").
- `follow_up`: session `chatbot_last_context` exists AND (message is short with deictic/pronoun signals OR retrieval scores below threshold).
- everything else: `content_query`.

*Why over keeping the LLM classifier:* the classifier is the cheapest stage to make deterministic — its 6 categories are shallow surface patterns; a 3B model adds seconds of latency for no accuracy gain on these. *Alternative considered:* keep a tiny-model LLM classifier (~1B) — rejected: still a network hop + inference per message, and hardest to test.

### D2: SQLite FTS5 replaces both `matchArticles` and the `pickSections` LLM calls
A new `ChatbotRetrievalService` owns a dedicated SQLite file (e.g. `storage/app/chatbot-index.sqlite`) with one FTS5 table: `sections(source_type, source_key, slug, heading, audience_group, body)`. Query flow: tokenize → stop-word filter → synonym-expand → FTS5 `MATCH` with BM25 ranking → filter by allowed audience groups → top 1–3 sections.

This retrieves at **section granularity directly**, eliminating the two-step article→section selection and its LLM calls entirely.

*Why FTS5 over improving the hand-rolled scorer:* BM25 handles term frequency/length normalization correctly, searches full bodies, and is battle-tested; hand-rolled scoring over bodies would reinvent it badly. *Why a separate SQLite file over the main DB's full-text search:* works identically regardless of the app's primary DB (MySQL/Postgres/SQLite), needs no migration on the main schema, and rebuilds are trivially atomic (write temp file, rename). *Alternative considered:* Laravel Scout database driver — rejected: adds a dependency and indirection for what is one table and one query.

### D3: Confidence-gated verbatim tier
After retrieval: if `top.score ≥ VERBATIM_MIN_SCORE` and (`no runner-up` or `top.score ≥ GAP_RATIO × runnerUp.score`), return the section content verbatim (trimmed to the 2000-char cap, prefixed with its article title). Otherwise make the single LLM call with the top sections as context (existing `answerFromSections` prompt, largely unchanged). Thresholds live in `config/ai-chatbot.php` and are tuned with a fixture test suite of representative queries.

*Why:* curated content is already user-facing prose; rephrasing it through an LLM adds latency and hallucination risk for zero benefit on unambiguous queries. The LLM earns its cost only when synthesis across sections or rephrasing to the question is needed.

### D4: Graceful degradation instead of 503
The `catch` around the LLM call returns the top retrieved section verbatim (HTTP 200) with a one-line "basic mode" notice, falling back to curated fallback sections when nothing was retrieved. The existing 503 path remains only for total failure (no retrieval, no fallback content).

### D5: Persistent section cache + rebuild command
`ChatbotHelpdeskService` parsing results are stored via Laravel's cache keyed by a content-hash of the helpdesk directory. A new artisan command `chatbot:index` (re)parses all content and rebuilds the FTS index atomically; it runs in the Docker entrypoint/deploy step. A content-hash check on cache read handles local dev edits without manual rebuilds.

### D6: Default model `llama3.2:3b`; keep provider abstraction
Only the config default changes; `laravel/ai`'s provider layer already supports Ollama and any OpenAI-compatible URL, so operators can point at `llama.cpp llama-server` for a true single-container deployment. Document both options in the README/deploy notes.

### D7: Delete `ChatbotDataService` and `ChatbotCaseService`
They are unreferenced by any runtime code path. Deleting (with their tests) rather than wiring them in keeps scope tight; `ARCHITECTURE.md` is corrected to describe the real pipeline. Revival, if ever wanted, is available in git history.

## Risks / Trade-offs

- [Lexical retrieval misses paraphrases that embeddings would catch] → Synonym map covers the domain vocabulary gap (OFW/Taglish terms); fallback sections catch total misses; the map is config-editable so support staff can add terms without code changes.
- [Heuristic intent rules misclassify edge cases the LLM handled] → Misclassification cost is low (worst case: a greeting goes through retrieval and gets a verbatim/LLM answer); fixture tests lock in behavior for known phrasings; rules are additive and cheap to extend.
- [Verbatim tier returns a correct-but-blunt section for a nuanced question] → Threshold is conservative (only clear single-section wins); everything ambiguous still gets the LLM.
- [3B model produces weaker prose than 8B] → Retrieval now carries relevance; the model only rephrases grounded content — the task where small models are adequate. Operators can raise `AI_CHATBOT_MODEL` on stronger hardware.
- [FTS index goes stale if content changes without rebuild] → Content-hash invalidation + deploy-time `chatbot:index`; index rebuild is idempotent and takes <1s for 32 articles.
- [SQLite FTS5 unavailable in the PHP build] → Verify `pdo_sqlite`/FTS5 in the Docker image at build time (the artisan command fails loudly); virtually all distro PHP builds bundle FTS5.

## Migration Plan

1. Ship new services + controller rewrite behind the existing `AI_CHATBOT_ENABLED` flag; API contract unchanged, so no frontend deploy coordination needed.
2. Add `chatbot:index` to the Docker entrypoint before `php-fpm` start.
3. Pull `llama3.2:3b` in the Ollama sidecar (or switch to `llama-server`); update `AI_CHATBOT_MODEL` default.
4. Rollback: revert the deploy — no schema migrations on the main database, the index file is disposable.

## Open Questions

- Verbatim-tier thresholds (`VERBATIM_MIN_SCORE`, `GAP_RATIO`) need empirical tuning against a query fixture set during implementation — initial values are placeholders.
- Whether the deployment allows an internet-reachable fallback provider (e.g. hosted API) as a secondary when local inference is down — assumed **no** (data residency); degrade to verbatim mode instead. Confirm with the operator.
