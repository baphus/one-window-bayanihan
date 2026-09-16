# DB Schema Dead-Table Audit — 2026-09-16

> **Source:** live PG schema dump (`database-schema`, 53 app+framework tables), `database/migrations/` (60 files), `app/Models/` (40 models), `app/` grep, `docs/DATA_MODEL.md v2.2.0`, `docs/API_CONTRACTS.md`, `docs/ARCHITECTURE_v2.2.0.md`
> **Goal:** find dead tables, dead references, drift. No code changed.

## Summary

| # | Finding | Verdict | Risk if removed |
|---|---------|---------|-----------------|
| 1 | `chatbot_embeddings` (pgvector + FTS) | **DROPPED 2026-09-16** (`2026_09_16_000001`; `DATA_MODEL.md` + `.env.docker.example` updated) | Done — verify on deploy with `SELECT to_regclass('chatbot_embeddings')` (expect null) |
| 2 | Legacy feedback stack: `feedback`, `feedback_servqual_responses`, `servqual_configs`, `feedback_invitations` | **RETIRED 2026-09-16** (`2026_09_16_000002`; exports, seeders, docs, helpdesk updated — see research note) | Done — verify on deploy with `SELECT to_regclass('feedback')` (expect null) |
| 3 | `referral_messages` + `agency_thread_reads` missing from live DB | **DRIFT, NOT DEAD** — code active, migration `2026_09_08_000001` not applied on inspected DB | High if ignored — feature will 500; just run migrations |
| 4 | `notifications`, `email_logs`/`email_events`, `audit_archives`/`audit_chain_checkpoints`, `case_number_counters`, `generated_documents`, `user_invites`, `system_settings`, `case_notifications`, `case_events`, `milestones`, `referral_client_*`, `case_category` pivot, `referral_services`/`referral_service_requirements`, `survey_*` | **LIVE** — confirmed active paths (see §3) | Do not drop |
| 5 | Dual-write columns: `referrals.required_services` (text) vs `referral_services` pivot; `cases.category_id`/`case_issue_id` vs `case_category` pivot; legacy names `agcy_id`, `refr_id` | **DEAD-ISH REFERENCES, NOT TABLES** — old column kept as display fallback | Low-medium — document canonical source, deprecate slowly |
| 6 | Docs still point at dead code: `API_CONTRACTS /feedbacks → FeedbackController`, `REQUIREMENTS_TRACEABILITY FR-FBK-001/002`, `ARCHITECTURE FeedbackService` | **DEAD DOCS** | Low — fix docs when #2 decided |

## 1. Confirmed dead: `chatbot_embeddings`

- Migrations: `2026_07_26_120000_create_chatbot_embeddings_table` (with `vector(768)`) + `2026_07_26_134507_add_fts_to_chatbot_embeddings_table`.
- `docs/DATA_MODEL.md:115` itself says: *"no Eloquent model; retired from query path"*.
- Grep `app/` for `chatbot_embeddings|ChatbotEmbedding|DB::table('chatbot_embeddings')` → **0 hits** (only docs + migrations + seeders mention it).
- Active chatbot path is file-based: `ChatbotHelpdeskService` reads `resources/js/data/helpdesk/*.ts` + persistent cache; `AGENTS.md:56` says SQLite FTS5 + `php artisan chatbot:index` (`RebuildChatbotIndex` → `refreshCache()`). No PG vector read.
- **Done 2026-09-16:** dropped via `2026_09_16_000001_drop_chatbot_embeddings_table.php` (Pint + `MigrationConventionTest` 3/3 passed). `DATA_MODEL.md` row/section marked DROPPED, `.env.docker.example` pgvector comments removed. Nothing else referenced pgvector (no config keys, no `app/` reads).

## 2. Legacy feedback stack (needs product decision)

- Tables: `feedback`, `feedback_servqual_responses` (`2026_06_01_000004`), `servqual_configs` (same + `2026_07_04_000001` active/default cols + `2026_07_10_000001` service_id), `feedback_invitations` (`2026_07_04_000001`).
- Models: **none** — `app/Models/` has zero `Feedback*.php` / `Servqual*.php` (glob confirmed). `app/Http/Controllers/` has zero `*Feedback*.php`, zero `*Servqual*.php`.
- Docs claim controllers that don't exist: `FeedbackController`, `PublicFeedbackController`, `AdminFeedbackController`, `AgencyServqualConfigController` (`ARCHITECTURE_v2.2.0:267`, `API_CONTRACTS:335-338`, `REQUIREMENTS_TRACEABILITY FR-FBK-001/002`).
- Only live code refs are raw exports: `DataExportQueries:1101,1135,1144-1148` (`DB::table('feedback')`, subselects on `feedback_servqual_responses`) + seeders (`TestingSeeder:926,958`, `StagingSeeder` chunk inserts + `feedback_servqual_responses` volume model).
- Replacement `survey_*` stack is fully live: models (`SurveyForm/Question/Invitation/Response`), controllers (`SurveyFormController`, `SurveyResponseController`, `PublicSurveyController`), services (`SurveyFormService`, `SurveyInvitationService`), listener `SendSurveyRequest`, `config/audit.php` observed models.
- **Interpretation:** old SERVQUAL stack superseded by `survey_*` around 2026-07-14, kept for history/exports. Not safe to call "dead" unilaterally.
- **Options:** (a) keep read-only for history + fix docs to say legacy; (b) migrate history into `survey_*` then drop; (c) drop if product confirms exports can move to `survey_*`.
- **Needs your call:** which option?

## 3. Drift (not dead): `referral_messages` + `agency_thread_reads`

- Migration `2026_09_08_000001_create_referral_message_tables.php` creates both + RLS policies.
- Code fully active: `ReferralMessage` model, `AgencyThreadRead` model (query-builder via `ReferralMessageService`), `ReferralMessageController`, `routes/web.php:98-100`, `ReferralMessageTest`, docs `DATA_MODEL:96-97`.
- But **absent from the live schema dump** inspected today → inspected DB hasn't run this migration.
- `down()` also references ghost `referral_message_reads` (older file version) — harmless, cleanup only.
- **Recommendation:** run `php artisan migrate` on that DB; no code change. Verify with `SELECT to_regclass('referral_messages')`.

## 4. Confirmed LIVE (do not drop)

- `notifications` — all 10 `app/Notifications/*` use `via() → ['database',...]` + `toDatabase()`; `User` uses `Notifiable`; `NotificationService:114,209` reads `$notifiable->notifications()`. Live.
- `email_logs` / `email_events` — `EmailEventSubscriber`, `ResendWebhookController:77-79`, `EmailLogController`, `PruneEmailLogs`, `SyncFailedEmails`, `VerifyMailTransport`. Live.
- `audit_archives` / `audit_chain_checkpoints` — `AuditArchiveService`, `ArchiveAuditLogs` (`audit:archive`), `PruneAuditLogs` (`audit:prune`), `VerifyAuditChain`, `RepairAuditChain`. Live.
- `case_number_counters` — `CaseNumberGenerator:71` atomic `INSERT ... ON CONFLICT`. Live.
- `generated_documents` — `GenerateSystemReport:50` + `GeneratedDocument` model. Live.
- `user_invites` — `UserService`, `AdminUserController`, `RegisterViaInviteController`. Live.
- `system_settings` — `SystemSettingsController`, `SecuritySettingsService`, `CaseController`, `ReferralController`, chatbot `reindexChatbot`. Live.
- `case_notifications` — `NotificationService`, `TrackingService:194`, `OfwDashboardController`, `ReferralClientRequestController:328`. Live.
- `case_events`, `milestones`, `referral_client_requests/items/messages/attachments/access_links`, `case_category` pivot (`ReportsService:1304`, `ReportsExportService:386`), `referral_services` / `referral_service_requirements`, `survey_*`, `case_documents`, `referral_attachments/comments`, `clients/addresses/employments/next_of_kin`, core reference tables. All have model + service/controller refs. Live.
- Framework: `cache/cache_locks`, `jobs/job_batches/failed_jobs`, `sessions`, `password_reset_tokens`, `migrations`. Live.

## 5. Dead-ish columns / naming debt (no table drop)

- `referrals.required_services` (text) vs canonical `referral_services` pivot (`2026_07_28_000003`) + `referral_service_requirements` (`000004`): code still reads legacy col (`PeerReferralCreated:47`, `CaseEventRecorder:49`, `AuditLogFormatter`). Keep as display fallback; treat pivot as canonical.
- `cases.category_id` + `case_issue_id` vs canonical `case_category` pivot (`2026_07_17_000001`): `CaseService` dual-writes via `primaryCategoryId()`; reports read both. Same treatment.
- Legacy FK names retained: `users/agencies/services/referrals.agcy_id`, `milestones.refr_id`, `referral_comments.refr_id`. Working code — rename only with real migration budget.
- Already-dropped (no action): `referral_compliance_requirements` (`2026_07_18_000001`), `case_comments` (`2026_07_02`), `philippine_addresses` (`2026_07_08_000001`), `referrals.type`, `cases.escalated_at`. No live refs found.
- `servqual_configs.service_id`, `feedback_invitations.service_id` (`2026_07_10_000001`) sit on legacy tables — die with §2.

## 6. Proposed next steps (for review)

1. ~~Confirm prod row counts for `chatbot_embeddings`~~ — done 2026-09-16. Feedback stack retired same day per explicit approval (no migrate-history step; restore from backup if ever needed).
2. Decide §2 option (keep-legacy / migrate / drop).
3. `php artisan migrate` the DB missing `referral_messages` + fix `down()` ghost reference opportunistically.
4. After decisions: follow-up migration(s) + `DATA_MODEL.md` + `API_CONTRACTS.md` + traceability cleanup. This report makes no schema change.

---
*Open question for you: ready to run the missing migration on the stale DB (`referral_messages` + `agency_thread_reads`)?*
