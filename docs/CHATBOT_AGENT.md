# Helpdesk chatbot

The chatbot uses the installed Laravel AI SDK to search helpdesk articles, read
selected sections, and answer with supporting article links. Public agency facts
come from a separate read-only directory tool. It cannot access private cases or
change application records.

`ChatbotController` delegates to `ChatbotConversationService`. `ChatbotKnowledge`
filters the cached helpdesk corpus by the current server-resolved audience and
ranks token/phrase matches. `SearchHelpdesk` supplies candidates;
`ReadHelpdeskArticle` records successfully read content in a per-turn evidence
registry. `GetAgencyServices` records public directory evidence separately.
`ChatbotResponse` rejects unknown evidence IDs and builds canonical source links.
Source provenance is validated in code; factual interpretation still needs model
evaluation. Search rank is not presented as confidence in the generated answer.

## Configuration

Use `AI_CHATBOT_ENABLED`, `AI_CHATBOT_PROVIDER`, `AI_CHATBOT_MODEL` and the
selected provider's server-side key in `config/ai.php`. The model must follow
function calls and the structured JSON output contract. The agent includes an
explicit JSON instruction as well as the SDK schema because some compatible
providers do not enforce the schema. Invalid output fails safely.

Default budgets: 6 model steps, 8 tool calls, 6 distinct evidence sources,
24,000 content characters, 2,000 output tokens, and a 45-second total deadline.
Environment overrides are documented in `.env.example`. The deadline is applied
to every HTTP request with the remaining time using a scoped cloned HTTP factory;
the original factory is restored in `finally`. This implementation uses the
existing synchronous PHP request model, not concurrent coroutine execution.

History is limited to six recent messages for generation. Article hints are
revalidated and never count as evidence. The browser clears history on identity
changes and clears stale context on unsupported/unavailable replies. Quick-help
buttons use the same tool pipeline. Unknown facts produce an unsupported response;
provider failures produce an unavailable response rather than an unrelated excerpt.

## Refresh and migration

`php artisan chatbot:index` refreshes parsed helpdesk content. The old command and
admin action names remain compatible; neither calls an embedding service. File
content hashes invalidate cached parsing, including same-size edits. Directory
records are read directly.

The three historical vector migrations are retained as no-op migration identities
so new installations need only plain PostgreSQL. Their former dependent FTS work
is also retired. Already-migrated databases retain their old embedding table and
extension unused. No shared extension or business data is dropped. Removal of old
embedding data is a separate maintenance decision.

Rollback to the previous app version on an existing database can use the retained
embedding infrastructure and previous configuration. A fresh installation lacks
that infrastructure: disable the chatbot when reverting until a compatible
version is restored. Keep application rollback separate from business migrations.

## Verification and release

- `php artisan test --filter Chatbot` exercises tools, source validation, endpoint
  validation, directory scoping and retained content parsing tests. Feature tests
  use the dedicated `bayanihan_test` database, never the active development database.
- `npm run test:run -- resources/js/Components/ChatBot.test.jsx resources/js/Components/ChatbotSources.test.jsx`
  exercises visible links, legacy messages and context reset.
- `php artisan chatbot:evaluate` runs the 22 deterministic retrieval cases from
  the 30-case fixture set in `tests/Fixtures/chatbot-questions.json`.
- `php artisan chatbot:evaluate --live --case=tracking` sends one synthetic query
  to the configured provider; omit `--case` for the full set. It prints answers and
  sources for review, so use synthetic fixtures only. The command does not enable
  the feature automatically. Ensure test fixtures are available in the evaluation
  environment; they may be omitted from a production image.
- Automated live checks compare status and required source slugs. Review actual
  factual support, complete lists, multilingual answers and relevance manually;
  matching a source slug alone is not a correctness proof.

Before release, run the same set in staging with the intended model/configuration,
verify links in the browser, exercise provider failure and role transitions, and
record latency and tool counts from metadata-only application logs. Require no
unauthorized/forged sources, all unsupported cases to abstain, and at least 90%
correct supported answers. Use the existing deployment workflow after these pass.

The implementation follows the official [Laravel AI SDK documentation](https://laravel.com/framework/docs/13.x/ai-sdk)
and the locally installed SDK contracts.
