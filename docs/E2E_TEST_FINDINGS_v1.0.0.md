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

### D2 — Employment `position` and `last_position` are also discarded

Typed "Construction Worker" into the "Select or type position…" control, whose
placeholder invites free text. `draft_client_data.employment.position` and
`.last_position` are both `null`. Same silent-loss class as D1; likely the same
root cause in the draft persistence path. `employer_name` and `last_country`
persisted correctly, so the loss is field-specific rather than wholesale.

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

## 4. Blocked by D1

These could not be exercised because no case can be published, and referrals are
created from published cases:

- Referral creation (case manager → agency)
- Referral acceptance and compliance requirements (agency)
- Agency RLS scoping across the 33 policies on 11 tables
- Client request inbox and the S3 document upload path
- Overdue referrals

The direct case-creation path (case manager creating a case rather than reviewing
a self-filed one) may set `sex` correctly and offer a way around D1; that is the
next thing to try.

## 5. Changelog

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-07-27 | Initial findings from the first end-to-end pass on AWS staging. Records the automated suite result, twelve verified flows, and six defects — D1 being a critical silent-data-loss bug that blocks the OFW self-filing feature end to end. |
