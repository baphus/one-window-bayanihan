# End-to-End Test Findings — AWS Staging

> **Version:** 1.0.0 | **Date:** 2026-07-27
> **Environment:** `https://bayanihan-staging.m317gkz7tgsqm.ap-southeast-1.cs.amazonlightsail.com`
> **Image:** `8f4d1d36ae14725661a05e70fff704d8856ffdde` (deployment v4)
> **Method:** real browser driving the deployed application, with every claim
> verified against the RDS database or the HTTP request/response payload.

## 1. Automated suite

`php artisan test` (serial): **1,338 passed, 5,538 assertions, 0 failures**, 5m34s,
exit 0. This includes the `routes/auth.php` rename made during deployment.

ParaTest is not installed, so `--parallel` fails with a `RequirementsException`.
Installing `brianium/paratest` would cut suite time materially.

## 2. Flows verified working

| Flow | Result | Evidence |
|---|---|---|
| Public landing, `/login`, `/help`, `/track` | PASS | HTTP 200, valid TLS |
| Admin login | PASS | Reached `/dashboard`; rotated bcrypt hash validated by `Hash::check` |
| MFA enrolment (TOTP) | PASS | Generated RFC 6238 code from the displayed secret; enrolment accepted and the enforcement gate cleared |
| OFW self-filing intake, all 6 steps | PASS | `cases` 0 → 1, case `OWB-2026-00001`, `source = self_filed` |
| Intake email verification OTP | PASS | Code delivered via the `log` mailer; `email_logs` 0 → 1 |
| PSGC cascading address lookup | PASS | Region → Province → City → Barangay each populated from the public API |
| Encrypted PII round-trip | PASS | `clients.date_of_birth` stored as a Laravel encrypted payload and correctly displayed as "May 15, 1990 (36 yrs)" |
| Multi-category classification | PASS | `case_category` pivot = 2 rows, `category_id` mirror also set |
| Intake queue listing | PASS | Case appears with correct number, name, email, phone, computed age |
| Queue worker draining | PASS | `jobs` and `failed_jobs` both 0 after each action |
| Audit chain | PASS | `audit_logs` 0 → 9, entries visible in the admin dashboard feed |
| Dashboard aggregates | PASS | Agencies 9, users by role 1/1/1, "3 of 3 users verified" |

## 3. Defects

### D1 — CRITICAL: `sex` is silently discarded, making self-filed cases unpublishable

**Impact:** No OFW self-filed case can be published. The entire self-filing
feature terminates at the case manager review step.

**Chain of failure:**

1. The intake wizard's Sex `<select>` has options `Male` / `Female` with **no
   placeholder option**. The browser therefore renders "Male" as selected while
   the underlying form state is null, so the OFW sees an answered control and
   submits nothing. `clients.sex` is created NULL.
2. `POST /cases/{case}/publish` rejects with **422**:
   `{"errors":{"draft":["Complete the draft before publishing. Missing: Sex."]}}`
3. The case manager uses the review page's Personal Info **Edit → Save**. The
   request body correctly contains `"sex":"Female"` and the server returns
   **200 OK** — but `cases.draft_client_data.sex` remains `null` and
   `clients.sex` remains NULL. **The backend accepts the field and drops it.**
4. Publish therefore fails permanently. There is no UI path to recover.

**Evidence:**

```
PUT /cases/061fb4e0-.../save-draft  →  200
request body: {"first_name":"Juan",...,"sex":"Female",...}

SELECT sex FROM clients            →  NULL
cases.draft_client_data.sex        →  null
POST /cases/061fb4e0-.../publish   →  422 "Missing: Sex."
```

**Fix required in two places:** add a placeholder option to the intake Sex
control so the field is genuinely required and cannot submit a phantom default;
and make `save-draft` persist `sex` rather than returning 200 while discarding it.
A `save-draft` endpoint that returns success without writing the submitted field
is the more serious of the two — it silently loses data.

### D2 — WITHDRAWN (tester error), leaving a minor UX note

Originally reported as employment `position` being silently discarded. On
re-testing this was **my input error, not a defect**. The "Select or type
position…" control offers two options once you type: the canonical entry
(e.g. "Domestic Helper / Household Service Worker") and an explicit
`Use "<your text>"` option for free text. Typing alone does not commit a value;
one of the options must be chosen. When selected properly the value persists
correctly.

The remaining note is minor UX: a required field where typing *looks* sufficient
but silently registers nothing is easy to get wrong, and the intake wizard gave
no validation warning about the empty position on submit.

### D2b — Case-creation wizard displays identifiers that are then discarded

Step 2 of the case-creation wizard displays two "auto-generated identifiers" in
editable-looking text boxes:

```
Case No.     CM-20260727-8598
Tracking ID  OWBAP-XY57RYX
```

The values actually persisted were **`OWB-2026-00002`** and **`OWBAP-WNURBAA`**.
Both displayed identifiers are thrown away and regenerated server-side on save,
and the case number even uses a different prefix scheme (`CM-` vs `OWB-`).

**Impact:** a case manager who notes the tracking ID during intake to give the
client — which the surrounding copy invites, since tracking is how the OFW checks
status — will hand over an ID that does not exist. Either show the real values
after creation, or do not show them until they are final.

### D3 — Intake review page shows raw PSGC codes instead of place names

The case manager review screen renders:

```
Resolved Address: 0730600041, 0730600000, 0702200000, 0700000000
```

Storing codes is correct; displaying them is not. A reviewer sees four numbers
instead of "Lahug, City of Cebu, Cebu, Region VII (Central Visayas)". The
project already has `app/Services/AddressNameResolver.php` for this, and it is
not applied on this screen. CodeGraph reports **no covering tests** for that
service, which is consistent with the regression going unnoticed.

### D4 — PSGC city search fails on the name every Filipino actually types

Typing **"Cebu City"** into City/Municipality returns "No results found". The
PSGC canonical name is **"City of Cebu"**. This affects every highly urbanised
city: "Mandaue City" → "City of Mandaue", "Lapu-Lapu City" → "City of Lapu-Lapu",
"Davao City" → "City of Davao". The autocomplete does a plain substring match, so
the most natural input silently returns nothing and the user cannot proceed.

Suggested fix: normalise both sides of the comparison so `"<X> City"` also
matches `"City of <X>"`.

### D5 — MFA enrolment is enforced for ADMIN only

`MFA_ENROLLMENT_ENFORCEMENT_ENABLED=true`. The ADMIN account was redirected to
`/profile` and could not reach any route until TOTP was enrolled. The
CASE_MANAGER account logged straight through to `/dashboard` with no MFA and full
access to case data.

Case managers read and write OFW personal data — name, contact details, date of
birth, employment history, case narrative. If the intent is that anyone handling
personal data must use MFA, this is a gap (ISO 27001 A.5.17, A.8.5). If the
intent is admin-only, it should be stated explicitly, because the environment
variable name implies global enforcement.

### D6 — Intake wizard collects no supporting documents

The 6-step wizard has no upload step, so S3 object storage is never exercised by
the OFW path. Documents appear to arrive later through the agency's client
request inbox. Not necessarily wrong, but it means an OFW cannot attach a
contract or payslip at the moment they file, and the S3 write path stays
unverified until a document request happens.

## 4. Second pass — routing around D1 via direct case creation

The case-manager creation path **does** persist `sex` correctly, which both
confirms D1 is specific to the self-filed intake plus `save-draft`, and unblocked
the remaining flows.

| Case | Status | Source | `clients.sex` |
|---|---|---|---|
| `OWB-2026-00001` | DRAFT | `self_filed` | **NULL** — blocked by D1 |
| `OWB-2026-00002` | OPEN | `internal` | `'FEMALE'` |

| Flow | Result | Evidence |
|---|---|---|
| Case creation, 3-step wizard | PASS | `OWB-2026-00002` created with status OPEN; confirmation dialog required before commit |
| Multi-service referral creation | PASS | `referrals` 0 → 1, DMW selected, 2 services attached |
| Referral notification job | PASS | `jobs` returned to 0, `failed_jobs` stayed 0 — the queue path that was dead before the `HOME` fix |
| Agency sees only its own referral | PASS | Agency user's list showed exactly 1 referral, its own agency's; RLS scoping holds |
| Referral acceptance, with mandatory remark | PASS | `PENDING` → `PROCESSING`; a remark is enforced before confirmation |
| Case timeline / `case_events` | PASS | 3 events recorded across open → refer → accept |
| Audit chain across the whole sequence | PASS | `audit_logs` 0 → 32, no gaps |

## 4a. D1 fixed — root cause was a request-contract mismatch

The original diagnosis ("save-draft discards `sex`") was correct in effect but
incomplete in cause. `UpdateDraftRequest` validates client fields as `client.*`
(`client.sex`, `client.first_name`, …). `ReviewIntake.jsx` sent them **flat at the
top level**, so `validated()` stripped **every** client field. Because all those
rules are `nullable`, the request passed validation and returned 200 OK while
writing nothing.

The other fields were invisible casualties — they already held values from
intake, so losing the update changed nothing observable. Only `sex`, never set in
the first place, surfaced, and publish refuses a case without it.

Fixes committed in `a1ccff8`:

1. `ReviewIntake.jsx` nests the personal payload under `client`, matching the contract.
2. The intake wizard's Sex `<select>` gains an empty `Select…` option and a `*`
   marker, so the browser can no longer render a phantom "Male" over empty state.

Two regression tests added to `CaseDraftTest.php`. Note the pre-existing draft
tests all passed both before and after the fix, because **every one of them sent
the correct nested shape** — the contract mismatch was untested in either
direction. One new test pins `sex` round-tripping; the other documents that flat
client fields are silently ignored, so widening the contract must be deliberate.

## 4b. Admin flows

| Screen | Result | Evidence |
|---|---|---|
| Manage Users | PASS | All 3 users listed with correct role labels (System Admin / Case Manager / Agency Focal), agency linkage, Verified toggles, Edit/Deactivate, Invite User, Pending Invites |
| Manage Agencies | PASS | Renders, no console errors |
| System Settings | PASS | Renders |
| Active Sessions | PASS | Renders |
| Audit Logs | PASS | Renders |
| Reports | PASS | Renders |
| **Email Logs** | PASS | Five outbound emails, all `sent`, none failed — see below |
| MFA challenge on login | PASS | Admin sign-in required TOTP at `/login/mfa`; generated code accepted |

Email Logs is the strongest single piece of evidence that the notification
pipeline works end to end, because it shows both sides of the referral being
notified:

```
ofw.e2e@example.com          Your Verification Code                    sent
agency.e2e@bayanihan.gov.ph  Referral Created                          sent
rosa.santos.e2e@example.com  Update on Your Case (OWB-2026-00002)      sent (x2)
cm.e2e@bayanihan.gov.ph      Referral Status Changed                   sent
```

This is precisely the path that was dead before the `Dockerfile` `HOME` fix, when
the queue worker could not reach the database and `/up` still reported healthy.

**Caveat on coverage:** the admin screens above were verified to render with real
data and no console errors. Except for Manage Users, their **mutating**
operations (create/edit/delete an agency, change a setting, revoke a session,
run an export) were **not** driven. Treat this as a render-and-read sweep, not
full CRUD coverage.

## 4c. Resolution status of every defect

| # | Defect | Fixed in | Verified how |
|---|---|---|---|
| D1 | Self-filed cases unpublishable — client fields sent flat, stripped by `validated()`, 200 OK while persisting nothing | `a1ccff8` | **On the deployed app**: `OWB-2026-00001` moved DRAFT to OPEN |
| D1b | `publishDraft` skipped the client-update block entirely when `client_id` was already set, so reviewer corrections never reached the `clients` row | `afd95c7` | Test verified to fail without the fix: `Failed asserting that null is identical to 'FEMALE'` |
| D2b | Wizard displayed a case number and tracker it then discarded | `4dd9ae1` | Code removed; guard test pins the canonical format |
| D3 | Review screen showed raw PSGC codes | `4dd9ae1` + `14cd954` | ⏳ **Not yet confirmed on the deployed app** |
| D4 | "Cebu City" returned no results | `4dd9ae1` | 6 unit tests; ⏳ not yet confirmed on the deployed app |
| D5 | MFA enforced for ADMIN only | `4dd9ae1` | Deployed in v5 |
| D6 | Intake collects no documents | — | Open by design; S3 write path still unexercised through the app |
| — | Container runtime: missing `HOME`, deleted PSGC data, small FastCGI buffers | `c056a21`, `8f4d1d3`, `14cd954` | `HOME` and buffers confirmed on the deployed app; PSGC data fix pending v6 |
| — | Tracker format divergence: `IntakeService` emitted 8 hex chars with no `OWBAP-` prefix | `336defb` | Test verified to fail without the fix: `produced 'FA11E91B'`. Visible in staging data: `OWB-2026-00001` carries `DE5C82D3` |
| — | Case number year derived from UTC, rolling over at 08:00 PHT | `336defb` | Test verified to fail without the fix |
| — | Numbers recycled after hard delete; `MAX()` read-modify-write | `3b6d4ed` | Counter table; test asserts a force-deleted number is not reissued |

**D3 deserves a note on why it took two commits.** The first fixed the wiring — `draftResolvedAddress` carries PSGC codes for the cascade dropdowns and was being reused for display. That alone did not fix it, because `AddressNameResolver` reads its lookup table from `resource_path('js/data/philippine-addresses.ts')` and the Dockerfile deleted all of `resources/js` during cleanup. Every `resolve()` silently returned its input. So address-name resolution was broken in **every** containerised deployment, not just on that screen.

## 4d. Identifier allocation, post-review

An independent review of the identifier implementation corrected an assumption worth recording: **the concurrency control was already sound.** Both generators took `pg_advisory_xact_lock` on the same key inside a transaction, backed by unique constraints and a 3× retry on 23505. There was no race to fix.

What the review did surface:

- The tracker-format divergence above, which had shipped because only `CaseService` had a format assertion.
- Numbers were recycled after `forceDelete()`, since they came from `MAX()` of surviving rows while `audit_logs` still referenced the old case by string.
- The UTC year boundary.
- No `lock_timeout`, making the advisory-lock wait unbounded.

Allocation is now one atomic `INSERT ... ON CONFLICT DO UPDATE ... RETURNING` against `case_number_counters`, with both services delegating to a single `CaseNumberGenerator`. **This does not shorten the serialization window** — the counter row lock is still held to commit, exactly as the advisory lock was. It fixes recycling and the read-modify-write.

Trackers moved to Crockford base32, 10 characters, uniform via `random_int`: ~50 bits, and no `0`/`O` or `1`/`I` confusion when a client reads one aloud to support.

## 5. Still not exercised

- **Admin flows** — users/invites, agencies, services, statuses/categories/issues,
  system and security settings, data export, maintenance mode, active sessions.
  The admin dashboard and audit log views were confirmed working, but the
  mutating admin screens were not driven.
- **Client request inbox and S3 document upload.** No document was uploaded in
  any flow, so the S3 write path remains unverified end to end. The bucket
  itself was proven writable with the application's IAM credentials during
  provisioning, but not through the application.
- **Overdue referrals** — needs a referral older than five days.
- **Survey / feedback invitations**, reports export, case trash and restore.

## 5. Changelog

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-07-27 | Initial findings from the first end-to-end pass on AWS staging. Records the automated suite result, twelve verified flows, and six defects — D1 being a critical silent-data-loss bug that blocks the OFW self-filing feature end to end. |
