## ADDED Requirements

### Requirement: Full-text section retrieval via SQLite FTS5
The system SHALL index every parsed helpdesk article section and guide topic (heading plus full body text) into an in-process SQLite FTS5 index and SHALL answer content queries by ranking sections with BM25 — using no vector database, embedding model, or external search service.

#### Scenario: Body-text match found
- **WHEN** a user's question matches terms that appear only in a section's body (not its title or excerpt)
- **THEN** the retrieval returns that section among the ranked results

#### Scenario: Ranked multi-section results
- **WHEN** a content query matches multiple sections
- **THEN** the system returns the top-ranked 1–3 sections ordered by BM25 relevance score

#### Scenario: No match falls back to curated sections
- **WHEN** a content query matches no indexed section above the minimum score threshold
- **THEN** the system falls back to the curated fallback sections (tracking portal and troubleshooting articles)

### Requirement: Domain synonym expansion
The system SHALL expand query terms through a configurable domain synonym map (e.g. OFW/Taglish terms: "OEC", "balik-manggagawa", "track"/"status"/"follow up") before querying the index.

#### Scenario: Synonym bridges vocabulary gap
- **WHEN** a user asks using a domain synonym not present verbatim in the content (e.g. "follow up" for "track")
- **THEN** the expanded query matches the relevant tracking sections

### Requirement: Audience-group filtering preserved
The system SHALL filter retrieval results by the requesting user's role-mapped audience groups (public, case manager, agency focal, admin) so users never receive content outside their allowed groups.

#### Scenario: Public user cannot retrieve staff content
- **WHEN** an unauthenticated user asks a question that matches an admin-only article
- **THEN** that article's sections are excluded from results and the best allowed match (or fallback) is used instead

### Requirement: Cached section parsing
The system SHALL parse the helpdesk TypeScript content files into sections at most once per content version, serving subsequent requests from a persistent cache, and SHALL rebuild the cache and FTS index when content changes (deploy-time command and/or content-hash invalidation).

#### Scenario: Repeat requests served from cache
- **WHEN** two chatbot messages arrive after the cache is warm
- **THEN** the second request performs no TypeScript file parsing

#### Scenario: Index rebuild on content change
- **WHEN** the index rebuild command runs after helpdesk content files change
- **THEN** the FTS index and section cache reflect the new content
