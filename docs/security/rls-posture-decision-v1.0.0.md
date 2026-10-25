# RLS Posture Decision (finding 12)

**Status:** Decided — Option B (honest documentation)
**Date:** 2026-10-25
**Owner:** Lead developer / ISM
**Supersedes:** the implicit claim (in several docs) that PostgreSQL row-level
security is an enforced defence-in-depth layer for the application runtime.

## 1. Decision

The application runtime continues to connect to PostgreSQL with the credentials in
the `DB_USERNAME` / `DB_PASSWORD` secrets (`.github/workflows/deploy.yml:157`). That
role is not constrained by this decision to be a non-owner role, and the
migrations are not guaranteed to have been run by a different role, so the runtime
must be assumed to own the tables it queries — and RLS is not forced on them (§2).
Under that identity:

- **Row-level security is NOT enforced for the application runtime.**
- **Agency/tenant isolation is enforced in the application layer only** — by
  `role:*` route middleware, `CheckRole`, controller authorisation, and
  `agcy_id` query scoping.

This posture is now documented explicitly wherever the docs previously implied
enforcement. No migration or deploy change accompanies this decision.

The two postures considered:

| | Option A — force RLS | Option B — honest docs (chosen) |
|---|---|---|
| Change | New migration creating a non-`BYPASSRLS` `app_runtime` role, `FORCE ROW LEVEL SECURITY` on `cases`, `clients`, `client_*`, `referrals`, `next_of_kin`, plus `deploy.yml` DB-credential change and secret rotation | Documentation + test-naming only |
| Isolation when the app layer has a bug | DB refuses the row | Bug is exploitable |
| Risk | Runtime role must own or be granted every object the migration set owns; a missed `GRANT` (sequence, function, extension) breaks production writes; `FORCE` also applies to helpers such as `pg_dump`-based restores and the retention/purge jobs | None — zero migration risk |
| Prerequisite | Production `DB_USERNAME` must not be `postgres`; pooling must stay off PgBouncer transaction mode | None |

Option A was rejected for this cycle because the enforcement half is a
redesign with deploy-coupled failure modes (grants, credential rotation,
PgBouncer), while the *documentation* half of the finding is the part that is
wrong today. Option A remains a valid future ticket; the operational
prerequisites are recorded in §4 so it can be picked up without re-analysis.

## 2. What the schema actually contains

Verified against `database/schema/pgsql-schema.sql` (the squashed source of
truth; the original `enable_row_level_security` / `rls_policies_remaining_tables`
migrations no longer exist as files).

**Enabled + policies, but NOT forced** — these are the tables where isolation
looks present but is bypassed by the owner:

| Table | Policies |
|---|---|
| `cases` | `admin_all_cases`, `case_manager_all_access_cases`, `agency_referred_cases` |
| `clients` | `admin_all_clients`, `case_manager_all_access_clients`, `clients_agency_referred` |
| `client_addresses` | `admin_all_client_addresses`, `case_manager_all_access_client_addresses`, `client_addresses_agency_referred` |
| `client_employments` | `admin_all_client_employments`, `case_manager_all_access_client_employments`, `client_employments_agency_referred` |
| `next_of_kin` | `admin_all_next_of_kin`, `case_manager_all_access_next_of_kin`, `next_of_kin_agency_referred` |
| `referrals` | `admin_all_referrals`, `case_manager_all_access_referrals`, agency policy |
| `milestones` | `admin_all_milestones`, `case_manager_all_access_milestones`, `milestones_agency_referred` |
| `referral_attachments` | `admin_all_referral_attachments`, `case_manager_all_access_referral_attachments`, agency policy |
| `referral_comments` | `admin_all_referral_comments`, `case_manager_all_access_referral_comments`, agency policy |
| `case_documents` | `admin_all_case_documents`, `case_manager_all_access_case_documents`, `case_documents_agency_referred` |
| `case_notifications` | `admin_all_case_notifications`, `case_manager_all_access_case_notifications`, `case_notifications_agency_referred` |

**Forced** (`FORCE ROW LEVEL SECURITY`, so the owner is subject to the policies
too) — 7 tables, all on the referral-client inbox and the agency-to-agency
message threads:

`agency_thread_reads`, `referral_messages`, `referral_client_access_links`,
`referral_client_messages`, `referral_client_message_attachments`,
`referral_client_requests`, `referral_client_request_items`.

Those tables are additionally reached by the public tracking paths, which run
without a logged-in user, so the policies on them are written to allow
`current_setting('app.user_role', true)` to be unset. `ReferralInboxSecurityRemediationTest`
pins the `FORCE` flag for four of them. Everything else in the list above is
enabled-but-not-forced, and is the subject of this decision.

## 3. What "application-level scoping" means in this codebase

The enforced controls, in order of evaluation:

1. **Route middleware** — `role:CASE_MANAGER,ADMIN`, `role:AGENCY`, etc. over
   `app/Http/Middleware/CheckRole.php` reading `users.role`.
2. **Controller authorisation** — explicit ownership checks (`CaseController@show`
   verifies the agency has an active referral on the case; report endpoints
   require `agcy_id` for agency users).
3. **Query scoping** — agency-facing query builders constrain `agcy_id` to the
   authenticated user's `users.agcy_id`.
4. **Session context (defence-in-depth only)** — `SetPostgresSession` sets
   `app.current_user_id` and `app.user_role` via parameterized `set_config` on
   every request. This is what makes the *forced* tables in §2 effective. For
   the enabled-but-not-forced tables it has no effect on the owner connection.

So: the RLS policies are a latent second layer that would become effective the
day the runtime connects as a non-owner role. Until then, **control 1–3 are the
only isolation**, and any missing scope check is a live vulnerability rather
than a backstopped one.

## 4. Prerequisites if Option A is picked up later

Recorded so the analysis is not repeated:

1. Confirm production `DB_USERNAME` is **not** `postgres`. If it is, Option A's
   premise has to be revisited first (the "runtime role" would be the superuser
   by definition).
2. Confirm the connection is **not** PgBouncer in transaction-pooling mode —
   `set_config(..., is_local=false)` session variables do not survive
   transaction multiplexing, which would silently break the *forced* tables that
   currently do work.
3. Create the runtime role without `BYPASSRLS` and grant it `SELECT/INSERT/UPDATE/DELETE`
   on every table **plus `USAGE/SELECT` on every sequence**, `EXECUTE` on
   functions used by triggers (audit-chain append, append-only guard), and
   `USAGE` on the `pg_trgm`/`uuid-ossp`/`pgcrypto` extensions. A missed grant is
   a production write outage, not a degraded mode.
4. Add `ALTER TABLE ... FORCE ROW LEVEL SECURITY` for the 11 tables in §2,
   including `case_notifications` (the notification fan-out reads across cases
   and would need a dedicated policy).
5. Re-verify the retention and purge jobs (`cases:purge-trashed`,
   `storage:cleanup-orphans`) and `AuditLog` chain writing, which read across
   tenant lines by design and would need an explicit bypass path.
6. Negative tests: an agency user must get zero rows for a foreign case with
   the *application* scoping removed, not just with it present.

## 5. Consequence for the risk register

`docs/compliance/information-risk-register-v1.0.0.md` R-19 ("Tenant isolation")
lists "App-layer scoping (primary today)" as the existing control — that is
already accurate and needs no change. What changes is any document that
presented RLS as an enforced layer. See ticket 21 for the edits.
