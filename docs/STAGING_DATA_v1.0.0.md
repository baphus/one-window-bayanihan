# Staging Data (StagingSeeder)

> **Version:** 1.0.0 | **Updated:** 2026-09-15
> **Source of truth:** `database/seeders/StagingSeeder.php`, `database/seeders/Staging/{StagingDataFactory,TemporalEngine,VolumeModel,AuditChainWriter}.php`, `config/staging.php`
> **Scope:** how the 6-month deterministic staging demo dataset is built, guarded, and verified. Test fixtures live under `tests/` (`TESTING_STRATEGY_v2.1.0.md`); reference seeders (`AgencySeeder`, `ServiceSeeder`, `CaseCategorySeeder`, `CaseIssueSeeder`, `ProductionSeeder`, `SystemSettingSeeder`) are idempotent inputs, not owned output.

## 1. What it is

`StagingSeeder` builds ~6 months of realistic, temporally coherent demo data ending on the most recent business-day 17:00 (Asia/Manila) anchor. Expected footprint **~115k rows (~45k chained audit digests) in 1–3 minutes** on staging hardware. Attachments/documents seed **metadata rows only** (placeholder `file_path`) — no object-storage writes.

## 2. Safety first

- `guardEnvironment()` refuses every environment outside `config('staging.seeder_allowed_envs')` (default `staging,local`; override `STAGING_SEEDER_ALLOWED_ENVS`). **Never production** — the seeder truncates business tables.
- Run: `php artisan db:seed --class=Database\\Seeders\\StagingSeeder` (staging/local only).
- Re-runs are clean and identical: all owned tables are `TRUNCATE … RESTART IDENTITY CASCADE` inside **one transaction**; the RNG is a fixed `mt_rand()` stream (seed `20260909`) with identical call order. (`Crypt`/`Hash` ciphertexts differ per run by nature — IV/salt — the logical data is identical.) Same-calendar-day re-runs are byte-identical; no stamp can leak into the future (capped at the anchor).

## 3. Design contracts

| Contract | How it holds |
|---|---|
| Determinism | One shared factory stream; fixed seed; identical call order |
| Temporal coherence | Every stamp derives from a previous one: case → referral → milestones/compliance → collaboration → notifications → emails → audit. Self-filed intake uses client hours (07:00–21:00), staff work agency hours (08:00–17:00); SLA chains guarded monotonic; young-case runway guard keeps mid-chain stages inside the window |
| PII fidelity | `date_of_birth`, street, employment, NOK contact/address, request bodies/snapshots encrypted with the models' exact set-paths (`EncryptedString`/`EncryptedDate`, `'encrypted'`, `'encrypted:array'`), so model reads decrypt transparently. Client names stay plaintext (matches the `Client` cast) |
| No side effects | Bulk query-builder inserts bypass Eloquent observers — no audit rows or notifications fire mid-run |
| Chain integrity | Audit rows written **last** via `AuditChainWriter`, then the run gates on `php artisan audit:verify` **outside** the transaction — non-zero exit throws with the dataset left in place for forensics |

## 4. What gets built (in order)

1. **Reference** (idempotent re-run): agencies, services (+ requirements), case categories/issues, production + system settings.
2. **Users** (reconciled via `updateOrInsert`, never truncated): `case@bayanihan.gov.ph` (CASE_MANAGER, DMW) · `admin@bayanihan.gov.ph` (ADMIN, DMW) · 9 AGENCY users (`owwa/dswd/doh/law-center-inc/province-cebu/tesda/city-cebu/dole/dmw @bayanihan.gov.ph`). Demo password: `P@ssw0rd!` (staging/local only).
3. **Clients + addresses + employments + next-of-kin**: window-spread stamps; first ~300 clients predate the window so rank-paired case owners almost always predate their cases (residual inversions nudged ≤24 h).
4. **Cases**: monthly distribution over the window (`VolumeModel::CASES`), Mon–Wed intake skew, self-filed (15%) vs internal; rank-paired owner assignment; oldest 720 closed out (240 ARCHIVED + 480 CLOSED), newest ~10% DRAFT (half clientless walk-ins), middle OPEN; small OPEN-only soft-deleted subset (duplicate-intake reason).
5. **Referrals + milestones + service requirements + service links**: 2 referrals per non-DRAFT case; OPEN mix PENDING / PROCESSING / FOR_COMPLIANCE; CLOSED/ARCHIVED explainable (first COMPLETED, second 90% COMPLETED else REJECTED); milestone templates per status; closed_at follows latest completion.
6. **Collaboration**: comments (INTERNAL/AGY_ONLY, threaded replies, ~1% soft-deleted), attachments, case documents (metadata), notifications.
7. **Client requests / surveys / SERVQUAL feedback** (classic 22-item instrument when the settings default is empty), **email logs + delivery events**, then **audit trail + verify**.

## 5. Owned tables (truncated + rebuilt)

`audit_logs`, `audit_archives`, `audit_chain_checkpoints`, `clients`, `client_addresses`, `client_employments`, `next_of_kin`, `cases`, `case_category`, `case_documents`, `case_notifications`, `referrals`, `milestones`, `referral_comments`, `referral_attachments`, `referral_service_requirements`, `referral_services`, `referral_client_requests`, `referral_client_request_items`, `referral_client_messages`, `referral_client_access_links`, `survey_forms`, `survey_questions`, `survey_invitations`, `survey_responses`, `feedback`, `feedback_servqual_responses`, `email_logs`, `email_events`. CASCADE additionally clears non-seeded runtime dependents (comments/events/invitations/message attachments/generated documents) — a reseed is meant to wipe derived state. Users and reference tables are excluded by design.

## 6. Operate

| Task | Command |
|---|---|
| Seed staging | `php artisan db:seed --class=Database\\Seeders\\StagingSeeder` |
| Verify chain | `php artisan audit:verify` (also runs automatically post-seed) |
| Allow another env | `STAGING_SEEDER_ALLOWED_ENVS=staging,local` |
| Inspect counts | seeder `report()` output (actual inserted counts per table) |

If `audit:verify` fails post-seed, keep the dataset for forensics and treat it as a seeder/chain bug — do not hand-fix rows.

---

## Changelog

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-09-15 | Initial document. |
