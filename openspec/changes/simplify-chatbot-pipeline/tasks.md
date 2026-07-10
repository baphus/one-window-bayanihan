## 1. Foundation: caching + retrieval index

- [x] 1.1 Add persistent section-parse caching to `ChatbotHelpdeskService` keyed by a content-hash of `resources/js/data/helpdesk/` (Laravel cache), replacing per-request-only caching
- [x] 1.2 Create `ChatbotRetrievalService` with a dedicated SQLite FTS5 index file (`storage/app/chatbot-index.sqlite`): schema `sections(source_type, source_key, slug, heading, audience_group, body)` and atomic rebuild (write temp file, rename)
- [x] 1.3 Implement indexing of all helpdesk article sections (from `ChatbotHelpdeskService::parseSections`) and guide topics (from `ChatbotGuideService`) with their audience groups
- [x] 1.4 Implement query path: tokenize → stop-word filter → synonym expansion → FTS5 MATCH with BM25 → audience-group filter → top 1–3 sections with scores
- [x] 1.5 Add domain synonym map to `config/ai-chatbot.php` (OEC, balik-manggagawa, track/status/follow-up, agency short names, etc.)
- [x] 1.6 Create `chatbot:index` artisan command that rebuilds cache + FTS index; fail loudly if FTS5 is unavailable in the PHP build
- [x] 1.7 Unit tests: index build, BM25 ranking on body-text matches, synonym expansion, audience filtering, no-match fallback threshold

## 2. Intent detection (heuristic, zero-LLM)

- [x] 2.1 Create `ChatbotIntentService` classifying greeting / identity / gibberish / follow_up / content_query per design D1 (EN + Filipino greeting lexicon, deictic follow-up signals, gibberish heuristics)
- [x] 2.2 Wire follow-up detection to session `chatbot_last_context` with fallback to full retrieval when no context is stored
- [x] 2.3 Unit tests with a fixture set of representative phrasings for each intent, including current `ChatbotTest` cases

## 3. Answer generation pipeline rewrite

- [x] 3.1 Rewrite `ChatbotController::message` pipeline: injection guard → heuristic intent → canned responses (zero LLM) → retrieval → verbatim tier or single LLM call; delete `classifyIntent`, `pickSections`, `matchArticles`
- [x] 3.2 Implement confidence-gated verbatim tier (thresholds `VERBATIM_MIN_SCORE`, `GAP_RATIO` in `config/ai-chatbot.php`), returning section content trimmed to the 2000-char cap with article-title prefix
- [x] 3.3 Implement graceful degradation: on LLM failure return top retrieved section verbatim (HTTP 200, "basic mode" notice); keep 503 only when nothing retrievable
- [x] 3.4 Preserve API contract: request validation, `{reply, actions?, error?}` shape, tracking-portal action link, session context storage — verify against `ChatBot.jsx` expectations
- [x] 3.5 Change default model in `config/ai-chatbot.php` to `llama3.2:3b`
- [x] 3.6 Feature tests: single-LLM-call assertion for content queries (fake/spy the agent), zero-LLM for canned intents, verbatim tier trigger and bypass, degraded mode, unchanged response shape; update `ChatbotTest` and `ChatbotKeywordMatchTest` (superseded by `ChatbotRetrievalTest` + rewritten `ChatbotPipeCleanTest`)
- [x] 3.7 Tune verbatim thresholds against a query fixture suite; record chosen values and rationale in config comments

## 4. Cleanup and deployment

- [x] 4.1 Delete `app/Services/Chatbot/ChatbotDataService.php`, `app/Services/Chatbot/ChatbotCaseService.php`, `tests/Feature/ChatbotCaseServiceTest.php`; remove any lingering references
- [x] 4.2 Correct the chatbot section of `ARCHITECTURE.md` to describe the new pipeline (and drop descriptions of the removed services)
- [x] 4.3 Add `chatbot:index` to the Docker entrypoint/deploy step; document model options (Ollama `llama3.2:3b` sidecar vs `llama.cpp llama-server` single-container) in deploy notes (`docs/DEPLOYMENT_GUIDE.md`, `.env.example`, `.env.docker.example`)
- [x] 4.4 Run full QA: `php artisan test` (1052 tests, 0 failures), endpoint-level checks for each intent path (greeting, content query verbatim, content query LLM, follow-up, LLM-down degraded mode) via feature tests; live-model smoke test deferred to an environment with Ollama running (not available on this machine)
