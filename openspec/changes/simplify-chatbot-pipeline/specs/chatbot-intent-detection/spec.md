## ADDED Requirements

### Requirement: Deterministic intent classification without LLM calls
The system SHALL classify each incoming chatbot message into exactly one intent — `greeting`, `identity`, `gibberish`, `follow_up`, or `content_query` — using deterministic rules (regex patterns, keyword lists, and token analysis) without making any LLM call for classification.

#### Scenario: Greeting detected heuristically
- **WHEN** a user sends a message consisting of a greeting phrase (e.g. "hello", "hi", "good morning", "kumusta") with no substantive question
- **THEN** the system classifies it as `greeting` and returns a canned greeting response without invoking the LLM

#### Scenario: Identity question detected heuristically
- **WHEN** a user asks who or what the assistant is (e.g. "who are you", "what can you do")
- **THEN** the system classifies it as `identity` and returns a canned identity response without invoking the LLM

#### Scenario: Gibberish detected heuristically
- **WHEN** a user sends a message with no meaningful tokens after stop-word removal (e.g. keyboard mash, "what?", empty semantic content)
- **THEN** the system classifies it as `gibberish` and returns a canned clarification response without invoking the LLM

#### Scenario: Content query passes through to retrieval
- **WHEN** a user sends a message containing meaningful tokens that is not a greeting, identity question, or gibberish
- **THEN** the system classifies it as `content_query` and proceeds to retrieval

### Requirement: Follow-up detection via session context
The system SHALL detect follow-up questions using the stored session context (`chatbot_last_context`) and deterministic signals (short queries, pronouns/deictic references, no strong retrieval match of their own) rather than an LLM call, and SHALL reuse the previously matched source when a follow-up is detected.

#### Scenario: Vague follow-up reuses previous article
- **WHEN** a user who previously asked about a specific article sends a vague follow-up (e.g. "what documents do I need?") that scores no strong retrieval match on its own
- **THEN** the system answers from the previously stored article/guide context

#### Scenario: No stored context falls back to full retrieval
- **WHEN** a follow-up-shaped message arrives but no session context is stored
- **THEN** the system processes it as a normal content query through retrieval

### Requirement: Prompt-injection guard retained
The system SHALL continue to reject messages matching the existing prompt-injection patterns before any intent detection or retrieval, returning a canned "irrelevant" response.

#### Scenario: Injection attempt blocked
- **WHEN** a message matches a blocked pattern (e.g. "ignore all previous instructions")
- **THEN** the system returns a canned refusal without classifying, retrieving, or invoking the LLM
