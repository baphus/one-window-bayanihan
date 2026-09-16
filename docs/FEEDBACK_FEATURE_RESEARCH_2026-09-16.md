# Feedback Feature Research — 2026-09-16

> Read-only research, no code changed. Question: is the legacy SERVQUAL feedback stack dead, and what keeps it alive?

## TL;DR

Two generations exist side by side. **Gen 2 (survey_*) is the live feature** end to end. **Gen 1 (feedback / feedback_servqual_responses / servqual_configs / feedback_invitations) has no write path, no routes, no controllers, no models** — the only live reads are the Excel/data exports. Everything else that says "feedback" (sidebar, dashboards, helpdesk, tests) actually runs on Gen 2.

## Gen 2 (live): survey_* stack

- Tables: `survey_forms`, `survey_questions`, `survey_invitations`, `survey_responses` (`2026_07_14_000001` + token-hash `000002`).
- Models, services, controllers all present: `SurveyFormService`, `SurveyInvitationService`, `SurveyFormController`, `SurveyResponseController`, `PublicSurveyController`.
- Routes (`routes/web.php`): `GET/POST /survey/{token}` (public, throttled), `/survey-forms` (AGENCY), `/surveys` (all staff).
- Trigger: `ReferralService:644` dispatches `ReferralCompleted` → `SendSurveyRequest` listener → checks `SystemSetting feedback_enabled` → dedupes on `survey_invitations` → picks agency's active form → creates invitation → queues `SurveyRequestMail`.
- Dashboard: `DashboardService:707` `feedbackPulse` reads `survey_invitations` (live counts); `avgServqual` is a hardcoded `null` placeholder (`DashboardService:396,417`).
- Sidebar "Feedback" section links to `/survey-forms` + `/surveys` — i.e. Gen 2 pages under a Gen 1 label.
- Tests: `PublicSurveyTest`, `SurveyFormControllerTest`, `SurveyResponseControllerTest`, `DashboardServiceTest` (pulse) — all Gen 2.

## Gen 1 (legacy): SERVQUAL stack

- Tables: `feedback`, `feedback_servqual_responses` (`2026_06_01_000004`), `servqual_configs` (+ `2026_07_04_000001`, `2026_07_10_000001`), `feedback_invitations` (`2026_07_04_000001`).
- **No models** (`app/Models/` has zero `Feedback*`/`Servqual*`). **No controllers** (docs name `FeedbackController`, `PublicFeedbackController`, `AdminFeedbackController`, `AgencyServqualConfigController` — none exist). **No routes** (no `/feedbacks*` in `routes/`). **No writes** (no `DB::table('feedback')->insert`, no mailable, no listener touches these tables).
- Only live reads (both in the export path):
  - `DataExportQueries::getFeedbacks` (`DB::table('feedback')`) → consumed by `GenerateSystemReport:118` (queued system report) and `DataExportController:42` (admin download).
  - `DataExportQueries::getFeedbackWithServqual` (joins `feedback_servqual_responses` for the 5 dimension averages) → export only.
  - `ColumnMaps:124` has a `feedback` sheet map (full-workbook export).
- Seeders still fabricate Gen 1 rows (`TestingSeeder:926,958`, `StagingSeeder` chunk inserts) — so staging/test exports show feedback data with no UI that could have produced it.

## Stale references to fix if Gen 1 is retired

- Docs: `API_CONTRACTS:335-338` (`/feedbacks*` → nonexistent `FeedbackController`), `REQUIREMENTS_TRACEABILITY FR-FBK-001/002`, `ARCHITECTURE FeedbackService` row.
- Helpdesk corpus (user-facing!): `building-servqual-feedback-questionnaires` describes a **SERVQUAL Configurations** page under **Feedback** that doesn't exist (real UI: Survey Forms); `feedback-dashboards-*` articles describe an agency Feedback Dashboard + admin Feedback Overview with SERVQUAL dimensions that don't exist as pages (real UI: `/surveys` response list); `configuring-system-settings-ai-chatbot` points at the same phantom page. These will confuse agency users.
- `DATA_MODEL.md` relationship diagram still lists `feedback`, `servqual_configs`, `feedback_invitations` under agencies/cases — needs a DROPPED or legacy marker per decision.
- Cosmetic: `DashboardService` `avgServqual => null` placeholder; sidebar "Feedback" label actually meaning Surveys.

## Options

1. **Keep read-only (cheapest):** leave tables for historical exports, mark Gen 1 legacy in `DATA_MODEL.md`, fix the two doc rows + helpdesk articles to describe the survey UI. Zero migration risk.
2. **Full retire:** drop the 4 tables (+ `getFeedbacks`/`getFeedbackWithServqual`/`ColumnMaps` entries + Gen 1 seeder inserts), fix docs + helpdesk. Loses historical export data — needs product sign-off and a prod row-count check first.
3. **Migrate history:** backfill Gen 1 rows into `survey_*` shape, then option 2. Most work; only worth it if historical SERVQUAL scores must survive in-app.

## Outcome (2026-09-16): option 2 (full retire) executed

- Migration `2026_09_16_000002_drop_legacy_feedback_tables.php` drops all four tables (child tables first).
- Removed: `DataExportQueries::getFeedbacks` + `getFeedbackWithServqual`, `ColumnMaps` feedback sheet, both `tableQueryMap` feedback entries, TestingSeeder feedback sections + comment pool + tracking vars, StagingSeeder feedback block + `SERVQUAL_INSTRUMENT` + `servqualQuestions()` + OWNED_TABLES entries + feedback audit chunk (method renamed `seedSurveyAudit`), `VolumeModel::FEEDBACK/SERVQUAL_RESPONSES/SERVQUAL_PER_FEEDBACK` + count rows, `SystemSettingSeeder` `default_servqual_questions`.
- Docs: `DATA_MODEL.md` rows/sections/diagram marked DROPPED, `API_CONTRACTS.md` feedback routes replaced with survey routes, `REQUIREMENTS_TRACEABILITY.md` FR-FBK-001–004 repointed at survey tables/routes.
- Helpdesk rewritten to the real survey UI: form builder, agency responses, CM/admin views, public flow; index titles/excerpts/tags, category blurb, glossary, and cross-article references updated.
- Kept deliberately: `AuditModule::FEEDBACK` enum + `feedbacks` alias (historic audit rows still decode), `StagingDataFactory::feedbackComment()` (survey text answers use it), versioned snapshots (`ARCHITECTURE_v2.1.0`, `DEPLOYMENT_COSTING_v1.0.0`, compliance docs) left as history.
