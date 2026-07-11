# Audit Strategy

> **Version:** 2.1.0 | **Updated:** 2026-07-12 | **Source:** `audit_logs` migrations, `AuditObserver`, `AuditLogController`, `AuditArchiveService`, `audit:*` commands
>
> Supersedes `AUDIT_STRATEGY.md` (v2.0.0), retained unchanged per document versioning policy.

## Changelog

**2.1.0 (2026-07-12)**
- Hash chain construction serialized with a Postgres transactional advisory lock (fixes concurrent-insert fork race); digest logic centralized in `AuditLog::chainDigest()`. Chain order is the `chain_seq` BIGSERIAL column (insertion order) — timestamps are second-precision and UUIDs are random, so `(timestamp, id)` cannot order same-second rows.
- New `audit:verify` command (scheduled weekly) validates the chain; checkpoint anchoring (`audit_chain_checkpoints`) keeps verification possible after pruning.
- Retention corrected and formalized: archive-then-prune lifecycle replaces both the "append-only forever" statement (v2.0.0 doc drift) and the unarchived hard-delete prune. `audit:archive` writes monthly gzipped NDJSON bundles + manifests to an S3-compatible disk before `audit:prune` may delete.
- New admin-only filtered CSV export of audit logs (explicit range, 30-day default, retention-capped, 100k-row guard, streamed, self-logged as `EXPORT`).
- New `category` column (`security`/`data`/`admin`/`system`) stamped at write time; viewer defaults to security+data+admin. Backfill runs automatically as a migration (`audit:backfill-categories` remains available for re-runs). Email changes are explicitly `security`.
- Prune enforces a contiguous oldest-first prefix: an older period that is unarchived or checksum-invalid blocks pruning of newer periods, so a single chain checkpoint always anchors verification.
- Export hardening: strict `Y-m-d` validation (malformed input → 422, self-logged) and spreadsheet formula-injection neutralization on CSV cells.
- Coverage expanded: `LOGIN_FAILED` events, MFA enable/disable, session termination, data exports; observers added on ServiceRequirement, CaseCategory, CaseIssue, CaseStatus, Feedback.
- Agency (AGENCY role) audit access removed entirely (was undocumented referral-scoped access).
- Verification query in v2.0.0 corrected: the actual chain digest covers id, action, module, entity_id, user_id, timestamp, old/new values, ip_address, and prev_hash.

**2.0.0 (2026-07-11)** — prior consolidated documentation (see `AUDIT_STRATEGY.md`).

## Design Principles

1. **Append-only** — Database trigger prevents UPDATE/DELETE on `audit_logs`; maintenance mutations require the `app.allow_audit_mutations` session flag
2. **Hash chain integrity** — Each entry stores the SHA-256 digest of the previous entry (`prev_hash`), serialized under `pg_advisory_xact_lock` so concurrent writes cannot fork the chain; verified on schedule
3. **Archive before delete** — No audit entry is deleted unless a finalized, checksum-verified archive bundle covers it
4. **Context-rich** — IP address, user agent, request correlation ID, and category on every entry
5. **Automatic** — `AuditObserver` captures model changes; `AuditLog::saving` stamps category and redacts secrets centrally
6. **Attributable extraction** — every export of audit data (and of the full data workbook) is itself an audit entry

## Schema (delta from v2.0.0)

```sql
ALTER TABLE audit_logs ADD COLUMN category VARCHAR(20)
    CHECK (category IN ('security', 'data', 'admin', 'system'));
CREATE INDEX idx_audit_logs_category_time ON audit_logs (category, timestamp DESC);

ALTER TABLE audit_logs ADD COLUMN chain_seq BIGSERIAL;  -- insertion-order chain key
CREATE UNIQUE INDEX idx_audit_logs_chain_seq ON audit_logs (chain_seq);

-- Allowed actions now:
-- CREATE, UPDATE, DELETE, LOGIN, LOGOUT, LOGIN_FAILED, EXPORT, ARCHIVE, UNARCHIVE, PUBLISH

CREATE TABLE audit_archives (          -- finalized monthly bundles
    id UUID PRIMARY KEY,
    period VARCHAR(7) UNIQUE,          -- 'YYYY-MM'
    path VARCHAR, checksum VARCHAR(64), row_count BIGINT,
    first_entry_at TIMESTAMP, last_entry_at TIMESTAMP,
    finalized_at TIMESTAMP, created_at TIMESTAMP, updated_at TIMESTAMP
);

CREATE TABLE audit_chain_checkpoints ( -- chain anchors written by audit:prune
    id UUID PRIMARY KEY,
    anchor_hash VARCHAR(64),           -- expected prev_hash of oldest surviving row
    pruned_through TIMESTAMP,
    bundle_manifest_path VARCHAR,
    created_at TIMESTAMP
);
```

## Event Categories

Stamped once in `AuditLog::saving` via `App\Services\AuditCategory` (single mapping, reused by the backfill):

| Category | Assignment |
|---|---|
| `security` | Modules `auth`, `security`, `session`, `mfa`; actions LOGIN, LOGOUT, LOGIN_FAILED |
| `system` | Unattributed writes from the console (seeders, scheduled jobs) |
| `data` | Business entities: case, client, address, employment, referral, milestone, attachment, feedback (and unknown modules, so nothing hides by default) |
| `admin` | Configuration/administration: user, agency, service, service requirement, case category/issue/status, exports, email logs, settings |

The admin viewer defaults to `security + data + admin`; `system` is opt-in. Legacy null-category rows remain visible whenever `data` is selected until `audit:backfill-categories` has run.

## Lifecycle

```
HOT (Postgres, 365 days — config audit.retention_days)
  │  audit:archive (monthly, 1st 01:00)
  ▼
ARCHIVE  YYYY/audit-YYYY-MM.ndjson.gz + .manifest.json
  │  on disk 'audit-archives' — S3-compatible in production
  │  (AUDIT_ARCHIVE_DRIVER=s3 + AUDIT_ARCHIVE_*/STORAGE_* envs);
  │  finalized only after re-read checksum verification
  │  audit:prune --force (monthly, 1st 02:30)
  ▼
DELETE — only rows covered by a finalized, checksum-reverified bundle;
         chain checkpoint written in the same transaction
```

`audit:verify` runs weekly (Mon 04:00): recomputes the chain over hot rows, anchoring at the latest checkpoint; failures exit non-zero and log at error level. **The archive bucket/directory must be included in backup scope — after pruning, bundles are the only copy of expired history.**

## Export

- `GET /audit-logs/export` — ADMIN only (route restricted to CASE_MANAGER+ADMIN; controller enforces admin).
- Explicit `date_from`/`date_to` required; UI pre-fills last 30 days (`audit.export.default_days`); range capped at the retention window; result sets over `audit.export.max_rows` (100k) are rejected with guidance before streaming.
- Streams CSV (timestamp ISO-8601, actor, actor email, action, module label, description, entity id, category, IP, JSON change summary). Redacted values stay redacted.
- Every attempt — success or rejection — is logged as `EXPORT` / module `audit` with the requested range, filters, and outcome. The full data workbook export (`data_export` module) is likewise self-logged.

## Audited Events by Module (delta)

| Module | Events | Trigger |
|--------|--------|---------|
| `auth` | LOGIN, LOGOUT, LOGIN_FAILED (bad credentials, inactive account, bad OTP) | Manual + `Failed` event listener |
| `mfa` | LOGIN_FAILED (bad TOTP/recovery code), enable/disable, recovery-code regeneration | Manual (`SecurityAuditLogger`) |
| `session` | DELETE (admin session termination) | Manual (`SecurityAuditLogger`) |
| `audit` | EXPORT (audit log exports, incl. rejections) | `AuditLogController` |
| `data_export` | EXPORT (full workbook) | `DataExportController` |
| `service_requirement`, `case_category`, `case_issue`, `case_status`, `feedback` | CREATE, UPDATE, DELETE | Observer (new) |
| _(all v2.0.0 modules)_ | unchanged | Observer / manual |

New entries use canonical singular module names (via `getAuditModuleName()`); the viewer and client scopes keep matching legacy aliases (`CASE`, `cases`, `case_files`, …) for historical rows.

## Access Control

- **ADMIN**: full audit viewer + export.
- **CASE_MANAGER**: viewer scoped to own cases and those cases' referrals; no export.
- **AGENCY**: no audit access (route-enforced; the former undocumented referral-scoped access was removed 2026-07-12).

## Operations Runbook

```bash
php artisan audit:verify                      # chain integrity (weekly via scheduler)
php artisan audit:archive [--days=] [--dry-run]
php artisan audit:prune  [--days=] [--dry-run] [--force]
php artisan audit:backfill-categories [--dry-run]   # one-time after deploy
php artisan audit:backfill-descriptions              # pre-existing command
```

Deployment order for this feature: migrate (includes the category backfill) → deploy code → set `AUDIT_CHAIN_VERIFIED_FROM` to the deployment timestamp (chain breaks from the pre-fix race era before this moment are reported as accepted anomalies, strict afterwards) → `audit:verify` baseline → scheduler active.
