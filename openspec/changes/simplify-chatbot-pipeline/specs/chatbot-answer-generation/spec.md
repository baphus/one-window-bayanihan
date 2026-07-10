## ADDED Requirements

### Requirement: At most one LLM call per message
The system SHALL make at most one LLM call per chatbot message — the final answer generation over retrieved section content. Intent classification and section selection SHALL NOT invoke the LLM.

#### Scenario: Content query uses a single LLM call
- **WHEN** a user asks a content question that retrieves multiple sections
- **THEN** exactly one LLM request is made (the answer generation), regardless of how many sections were retrieved

#### Scenario: Non-content intents use zero LLM calls
- **WHEN** a message is classified as greeting, identity, gibberish, or blocked
- **THEN** no LLM request is made

### Requirement: Zero-LLM verbatim answer tier
The system SHALL return curated section content directly (verbatim, markdown-formatted, trimmed to the response length cap) without an LLM call when retrieval confidence is high and the query resolves to a single section, as determined by a configurable score threshold and score-gap criterion.

#### Scenario: High-confidence single match answered verbatim
- **WHEN** a query's top retrieval result exceeds the confidence threshold and clearly outranks the runner-up
- **THEN** the system responds with the section's content directly, labeled with its article context, without invoking the LLM

#### Scenario: Ambiguous match uses the LLM
- **WHEN** a query matches several sections with similar scores
- **THEN** the system composes the answer with a single LLM call over the top-ranked sections

### Requirement: Graceful degradation when LLM unavailable
The system SHALL fall back to returning the top-ranked retrieved section verbatim (with a notice that the assistant is in basic mode) when the LLM backend is unreachable or errors, instead of returning an HTTP 503 error.

#### Scenario: LLM down but retrieval succeeded
- **WHEN** the LLM request fails after sections were successfully retrieved
- **THEN** the system returns the top section's content verbatim with HTTP 200

#### Scenario: LLM down and no retrieval match
- **WHEN** the LLM request would be needed but no sections matched
- **THEN** the system returns the curated fallback content or a helpful static message with HTTP 200

### Requirement: Small CPU-friendly default model
The default chatbot model configuration SHALL be a small model suitable for CPU-only inference on a low-resource host (default `llama3.2:3b`, ≤ ~2 GB RAM), overridable via the existing `AI_CHATBOT_MODEL` environment variable, and the answer prompt SHALL remain compatible with OpenAI-compatible local servers (Ollama or llama.cpp `llama-server`).

#### Scenario: Default model is small
- **WHEN** no `AI_CHATBOT_MODEL` environment variable is set
- **THEN** the configured model defaults to `llama3.2:3b`

#### Scenario: Operator overrides model
- **WHEN** `AI_CHATBOT_MODEL` is set to another model identifier
- **THEN** the system uses that model without code changes

### Requirement: Existing API contract and safety behavior preserved
The `POST /chatbot/message` endpoint SHALL keep its current request validation (message, bounded history) and response shape (`reply`, optional `actions`, optional `error`), the 2000-character response cap, session-based follow-up context storage, and grounding rules (no live case data claims, role-tailored answers).

#### Scenario: Response shape unchanged
- **WHEN** the frontend sends a valid message payload
- **THEN** the response contains a `reply` string and, when applicable, an `actions` array — identical in shape to the current contract

#### Scenario: Tracking portal action link still attached
- **WHEN** the matched source is the public tracking portal article
- **THEN** the response includes the "Go to Tracking Portal" action link
