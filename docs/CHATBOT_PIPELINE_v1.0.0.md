# Chatbot Pipeline

> **Version:** 1.0.0 | **Updated:** 2026-09-15 | **Source:** `app/Http/Controllers/ChatbotController.php`, `app/Services/Chatbot/` (11 services), `app/Http/Requests/ChatbotMessageRequest.php`, `config/ai-chatbot.php`, `config/ai.php`
>
> **Supersedes:** `docs/CHATBOT_AGENT.md` (retained as history). That document describes the same architecture; this record is now the authoritative reference for the request pipeline, the eleven services, the configuration surface, and the verification procedure.

## 1. Request Path

```
POST /chatbot/message
  → middleware: turnstile.session, throttle:chatbot (30/min)
  → ChatbotMessageRequest (validation)
  → ChatbotController::message (one-liner, 16 lines)
  → ChatbotConversationService::reply($validated, ChatbotAudience::forUser($user))
  → JSON: { reply, status, sources, actions, lastContext }
```

`ChatbotController` contains no logic beyond delegation. Two facts are established at the boundary and never re-derived from client input afterwards:

1. **The payload** — `message` (string, ≤ 1,000 chars), optional `history` (≤ 20 `{role: user|bot, text}` entries, each ≤ 1,000 chars), optional `lastContext` (`source_type`, `source_label`, `article_title`). `lastContext` is treated as a *hint*, never as evidence.
2. **The audience** — `ChatbotAudience::forUser($request->user())` resolves guest vs. authenticated role server-side. The client never declares who it is; role transitions must clear client-side chat state (see §6).

## 2. The Eleven Services

| # | Service | Responsibility |
|---|---------|----------------|
| 1 | `ChatbotAudience` | Server-side audience resolution (guest / role). Entry point for all permission filtering. |
| 2 | `ChatbotKnowledge` | Filters the cached helpdesk corpus by audience; ranks weighted token/phrase matches. No vector store, no embeddings. |
| 3 | `ChatbotConversationService` | Turn orchestration: normalize → intent → guide/retrieve → respond → suggestion. Owns the model-step/tool-call budgets and the 45-second deadline. |
| 4 | `ChatbotIntentService` | Classifies the turn (question, greeting, clarification-seeking, unsupported, helpdesk navigation). Decides tool vs. canned path. |
| 5 | `ChatbotGuideService` | Quick-help buttons and guided flows. Uses the same tool pipeline as free text — buttons are not a bypass. |
| 6 | `ChatbotResponse` | Builds the response envelope; rejects unknown/forged evidence IDs; emits canonical source links. Provenance is validated in code. |
| 7 | `ChatbotQueryNormalizer` | Query normalization before matching (casing, punctuation, common phrasing variants, multilingual equivalents). |
| 8 | `ChatbotHelpdeskService` | Helpdesk tool implementations: `SearchHelpdesk` (candidates) and `ReadHelpdeskArticle` (records successfully read content in a per-turn evidence registry). |
| 9 | `ChatbotRetrievalService` | Retrieval policy: candidate selection, per-turn evidence registry, article-hint revalidation (hints never count as evidence). |
| 10 | `ChatbotSuggestionService` | Follow-up suggestions attached to `actions`. Suggestions carry no evidence weight. |
| 11 | `ChatbotTurn` | Per-turn value object: validated input, resolved audience, evidence registry, budgets consumed. Evidence never leaks across turns. |

Supporting rule: only content **actually read and selected as support during the turn** may be cited. `SearchHelpdesk` candidates that were never read, client-supplied `lastContext` hints, and suggestion content are not evidence. There is no vector-confidence score anywhere in the output; search rank is not presented as confidence.

## 3. Retrieval Model: Weighted Token Match Over a Cached Corpus

There is **no vector database, no embedding service, and no SQLite FTS5** — all three were retired (the historical vector migrations remain in the migration history as no-op identities so existing databases keep working; fresh installs need only plain PostgreSQL, and already-migrated databases retain their unused embedding table/extension — no shared extension or business data is dropped, and removing old embedding data is a separate maintenance decision).

How retrieval works:

1. `php artisan chatbot:index` parses helpdesk content and warms the cache. File content hashes invalidate cached parsing, including same-size edits. Directory (agency) records are read directly, never cached.
2. `ChatbotKnowledge` filters the cached corpus to the current audience, then ranks **weighted token/phrase matches** (title/heading weight exceeds body weight; exact-phrase matches outrank scattered tokens).
3. `SearchHelpdesk` returns ranked candidates; `ReadHelpdeskArticle` reads the selected sections and registers them as turn evidence.
4. Public agency facts come from a separate read-only directory tool (`GetAgencyServices`), recorded as directory evidence with a null URL and a directory label — distinct from article evidence.
5. Generation history is capped at the six most recent messages. Article hints are revalidated every turn.

The chatbot cannot access private cases and cannot change application records. It has no write tools.

## 4. Response Contract

| Field | Shape |
|-------|-------|
| `reply` | Model-generated answer text (bounded by `max_tokens`) |
| `status` | `answered` \| `greeting` \| `clarification` \| `unsupported` \| `unavailable` |
| `sources` | Array of `{source_type, slug, heading, url, sections}` plus canonical `article_title` for helpdesk articles; directory references carry a null URL |
| `actions` | Follow-up suggestions (no evidence weight) |
| `lastContext` | Explicitly nullable `{source_type, source_label, article_title}` hint for the next turn; clients **must clear stored context on null** |

Failure semantics: unknown facts produce `unsupported` (abstain, never an unrelated excerpt); provider/model failures produce `unavailable`. Invalid model output (schema violation) fails safely to `unavailable`. Factual interpretation still needs model evaluation — code validates provenance, not truth.

## 5. Configuration (`config/ai-chatbot.php`, `config/ai.php`)

| Key (env) | Default | Bounds / Notes |
|-----------|---------|----------------|
| `enabled` (`AI_CHATBOT_ENABLED`) | `false` | Feature is off unless explicitly enabled |
| `provider` (`AI_CHATBOT_PROVIDER`) | `gemini` | Any of the 15 drivers in `config/ai.php` (anthropic, azure, bedrock, cohere, deepseek, eleven, gemini, groq, jina, mistral, ollama, openai, openrouter, voyageai, xai) |
| `model` (`AI_CHATBOT_MODEL`) | `gemini-flash-latest` | Must support function tools + structured JSON output |
| `temperature` (`AI_CHATBOT_TEMPERATURE`) | `0.2` | Low; factual answers over creativity |
| `max_tokens` (`AI_CHATBOT_MAX_TOKENS`) | `2000` | Floor of 500 |
| `timeout` (`AI_CHATBOT_TIMEOUT`) | `45` (s) | Clamped 1–120; applied to every model HTTP request against remaining time via a scoped cloned HTTP factory, restored in `finally`. Synchronous PHP request model — no coroutines. |
| `max_steps` (`AI_CHATBOT_MAX_STEPS`) | `6` | Clamped 2–8 model steps per turn |
| `max_tool_calls` (`AI_CHATBOT_MAX_TOOL_CALLS`) | `8` | Clamped 1–12 |
| `max_article_reads` (`AI_CHATBOT_MAX_ARTICLE_READS`) | `6` | Clamped 1–8 distinct evidence sources |
| `max_context_characters` (`AI_CHATBOT_MAX_CONTEXT_CHARACTERS`) | `24000` | Clamped 1,000–48,000 content characters |
| `assistant_name` (`APP_ASSISTANT_NAME`) | `Bayani` | Display name in the UI |

The provider key comes from the selected provider's server-side entry in `config/ai.php` — never exposed to the client. The agent sends an explicit JSON instruction alongside the SDK schema because some compatible providers do not enforce the schema. Environment overrides are documented in `.env.example`. The `chatbot:evaluate --live` command does not enable the feature.

## 6. Client Obligations

- Clear stored `lastContext` whenever the server returns null.
- Clear the whole chat (history + context) on identity or role changes (login, logout, role switch) — stale audience-scoped evidence must never persist.
- Quick-help buttons go through the same pipeline; no client-side answering or caching of replies.

## 7. Refresh, Rollback, and Migration Notes

- `php artisan chatbot:index` refreshes parsed helpdesk content. Old command and admin action names remain compatible; none call an embedding service.
- Rollback to a previous app version on an existing database can reuse the retained embedding infrastructure and previous configuration. A fresh install lacks that infrastructure: disable the chatbot when reverting until a compatible version is restored. Keep application rollback separate from business-data migrations.

## 8. Verification and Release

Backend (dedicated `bayanihan_test` database, never the dev database):

```bash
php artisan test --filter Chatbot   # tools, source validation, endpoint validation, directory scoping, content parsing
php artisan chatbot:evaluate        # 22 deterministic retrieval cases from the 30-case fixture set (tests/Fixtures/chatbot-questions.json)
php artisan chatbot:evaluate --live --case=tracking   # one synthetic query to the configured provider; omit --case for the full set
```

Frontend:

```bash
npm run test:run -- resources/js/Components/ChatBot.test.jsx resources/js/Components/ChatbotSources.test.jsx
```

Live checks compare status and required source slugs automatically, but a slug match is not a correctness proof — review factual support, complete lists, multilingual answers, and relevance manually, using synthetic fixtures only. Test fixtures may be omitted from a production image; ensure they exist in the evaluation environment.

Release gate (staging, with the intended model/configuration): verify links in a browser, exercise provider failure and role transitions, record latency and tool counts from metadata-only application logs. Require zero unauthorized/forged sources, abstention on all unsupported cases, and ≥ 90% correct supported answers. Then follow the existing deployment workflow.

Implementation follows the installed Laravel AI SDK contracts.
