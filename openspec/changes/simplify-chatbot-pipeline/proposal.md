## Why

The AI chatbot currently makes up to 5 sequential LLM calls per user message (1 intent classification + up to 3 section-picking calls + 1 answer generation) against `llama3:latest` (8B) via Ollama. On the target deployment — a single low-resource Docker host with CPU-only inference — this means 30s–2min response times and ~5–6 GB RAM just for the model. The retrieval layer only searches article titles/excerpts (not bodies), re-parses helpdesk `.ts` files with regex on every request, and two chatbot services (`ChatbotDataService`, `ChatbotCaseService`) are dead code never wired into the controller.

## What Changes

- **Collapse the pipeline to at most one LLM call per message**: replace LLM intent classification with deterministic heuristics (regex/keyword rules for greeting, identity, gibberish); replace LLM section-picking with the same keyword scoring already used for article matching, applied at section level.
- **Shrink the default model** from `llama3:latest` (8B, ~5–6 GB) to a small CPU-friendly default (`llama3.2:3b`, ~2 GB), configurable via existing `AI_CHATBOT_MODEL` env var.
- **Upgrade lexical retrieval — no vector DB**: index parsed helpdesk sections into SQLite FTS5 (in-process, BM25 ranking, full body-text search) and add a domain synonym map (OFW/Taglish terms like OEC, balik-manggagawa, "track"/"status") expanded before querying.
- **Add a zero-LLM verbatim-answer tier**: when retrieval confidence is high and the query maps to a single section, return the curated section content directly (instant, hallucination-free); the LLM is used only for rephrasing/multi-source answers. When the LLM is unavailable, degrade gracefully to verbatim sections instead of a 503.
- **Cache parsed helpdesk sections** instead of regex-parsing TypeScript content files on every request; invalidate on deploy/content change.
- **Remove dead code**: delete `ChatbotDataService` and `ChatbotCaseService` (and their tests), and correct `ARCHITECTURE.md` which describes features that don't run.
- **Preserved (unchanged behavior)**: prompt-injection regex guard, role-scoped audience filtering (`ROLE_AUDIENCE_MAP`), follow-up context via session, response length cap, frontend `ChatBot.jsx` API contract (`POST /chatbot/message` request/response shape).

## Capabilities

### New Capabilities
- `chatbot-intent-detection`: Deterministic (non-LLM) classification of user messages into greeting, identity, gibberish, follow-up, or content-query intents, with canned responses for non-content intents.
- `chatbot-retrieval`: Lexical full-text retrieval of helpdesk article sections and guide topics using SQLite FTS5 with BM25 ranking, synonym expansion, audience-group filtering, and cached section parsing — no vector database.
- `chatbot-answer-generation`: Answer composition with at most one LLM call per message, a zero-LLM verbatim tier for high-confidence single-section matches, and graceful degradation to verbatim content when the LLM backend is unavailable.

### Modified Capabilities

<!-- None — no existing main specs cover the chatbot; all chatbot capabilities are introduced by this change. -->

## Impact

- **Code**: `app/Http/Controllers/ChatbotController.php` (major rewrite of pipeline), `app/Services/Chatbot/ChatbotHelpdeskService.php` (caching, FTS indexing hooks), new retrieval/intent services; deletion of `app/Services/Chatbot/ChatbotDataService.php`, `app/Services/Chatbot/ChatbotCaseService.php`, `tests/Feature/ChatbotCaseServiceTest.php`.
- **Config**: `config/ai-chatbot.php` default model change; new config for retrieval thresholds/synonyms.
- **Tests**: `tests/Feature/ChatbotTest.php`, `tests/Feature/ChatbotKeywordMatchTest.php` updated; new tests for intent heuristics, FTS retrieval, verbatim tier, and degraded mode.
- **Docs**: `ARCHITECTURE.md` chatbot section corrected.
- **Infra**: Ollama sidecar remains supported but with a smaller model; optionally replaceable by `llama.cpp` `llama-server` via the existing OpenAI-compatible provider config. SQLite FTS5 requires no new service (bundled with PHP's `pdo_sqlite`).
- **Not affected**: frontend `ChatBot.jsx` (API contract unchanged), auth/session handling, helpdesk content files, prompt-injection guard, role-based audience filtering.
- **Compliance note (ISO 27001 / DPTM readiness)**: input validation (injection guard) and least-privilege content exposure (audience filtering) are retained; the verbatim tier improves auditability since responses become deterministic and traceable to source articles. No PII enters prompts (unchanged).
