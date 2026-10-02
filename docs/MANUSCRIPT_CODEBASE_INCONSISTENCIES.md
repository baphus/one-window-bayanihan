# Manuscript vs. Code — Codebase Inconsistencies

**This is the authoritative list.** Nothing else under `docs/` covers this ground.

**Scope:** only findings where the manuscript makes a claim about the system and the
code contradicts it. Manuscript-internal issues — grammar, citation years, name
forms, questionnaire wording, form completion, TOC naming — are **out of scope by
request** and are not listed.

Every finding was re-derived from the manuscript and checked against the repository by
independent adversarial verification. **Seven fabrications in the original audit were
caught and discarded**, including "exports do not exist" (they do), "unclosed
parenthesis" (none exists), and the webhook claim (the manuscript never mentions
webhooks). See **Retractions** before acting on anything.

---

## How to find each finding in Word

Every finding gives you a **chapter › section › table** path. To jump straight to the
text, each also has a **Find this** string — paste it into Word's Ctrl+F. Use the
quoted wording under **What it says** when the Find string could match elsewhere.

Code evidence uses real `file:line` references you can verify in the repo. Those are
exact. Manuscript locations are given by heading, not by line or page number.

---

## Document map

How the manuscript is organised, so the paths below make sense:

| Part | Sections |
|---|---|
| **CHAPTER I — PROJECT CONTEXT AND RESEARCH DESIGN** | Background of the Study · Purpose and Description · Objectives · Scope and Limitations (› Scope, › Limitations) · Methods (› Agile Methodology, › Environment, › Respondents, › Instruments, › Scoring Procedures, › Mean Interpretation) |
| **CHAPTER II** *(Related Literature and Systems)* | Related Literature (› Related Studies, › Related Systems) · Competitor Analysis · Comparative Matrix · Technical Background · Theoretical Concepts · Related Technologies |
| **CHAPTER III — METHODOLOGY** | organised in phases — see below |
| **Bibliography** | |
| **APPENDIX A** | signatures / acknowledgement |
| **APPENDIX B** | Capstone Project Team Composition Form |
| **APPENDIX C** | consultation and adviser acceptance |
| **APPENDIX D** | Feasibility Analysis Summary (› Technical, Organizational, Economic, Schedule Feasibility) |
| **APPENDIX E** | Capstone Project Comparative Matrix — duplicate of the Chapter II matrix |
| **APPENDIX F** | Research Working Title Form |
| **APPENDIX G** | research title form |
| **APPENDIX H** | List of Modules — duplicate of the Chapter III module table |
| **APPENDIX I** | System Quality User Acceptance Questionnaire — five ISO/IEC 25010:2023 instruments |
| **APPENDIX J** | proposal hearing outcome, compliance checklist, consultation logs, panelist required revisions |

### Chapter III phase structure

| Phase | Sections |
|---|---|
| **Planning Phase** | Requirement Specifications · Technical Feasibility · Organizational Feasibility · **Economic Feasibility** (› Cost Summary) · Schedule Feasibility · System Context Diagram · User Flow Diagram · Partner Agencies Program Flow · System Administrator Program Flow · Functional Decomposition Diagram · **Work Breakdown Structure** · Gantt Chart |
| **Analysis Phase** | Use Case Diagram · Sequence Diagram · Activity Diagram |
| **Design Phase** | User Interface Design · **Database Design** (› **Data Dictionary**) · System Architecture Design · **Network** (› Network Design, Network Deployment Diagram, Network Topology, Network Security, **Network Devices**) |
| **Development Phase** | Programming Environment (› Programming Language, Version Control, **Programmer/Module/Function table**, **Database Stack**, **Testing Area**, Configuration, Operating System, Hardware Requirements) |
| **Testing Phase** | Unit Testing · Integration Testing · System Testing · **Acceptance Testing** |
| **Deployment Phase** | Operational Environment (› Hardware Requirements, Operating System, Runtime Requirements, Network Requirements, **Database Server Environment**, User Environment Requirements) |
| *(unphased, after Deployment)* | **Security Requirements** · Documentations (› User Guide) · Conclusion · Installation Guide · Curriculum Vitae |

⚠️ **There is no Chapter IV.** The Methods section states *"The results are to be
presented and analyzed in Chapter 4"* — the document goes from Chapter III straight to
the appendices.

---

## The 11 findings

| # | Finding | Where | Severity |
|---|---|---|---|
| **1** | Data dictionary invents an ENUM type that does not exist | Ch. III › Design › Database Design › **Data Dictionary** | 🔴 Critical |
| **2** | SERVQUAL claimed as a shipped feature; path was retired | Ch. I › **Purpose and Description** + **Scope and Limitations › Scope**; App. I | 🔴 Critical |
| **11** | Login walkthroughs omit the password and describe email OTP instead of TOTP | Ch. III › Documentations › **User Guide**; Ch. II › **Comparative Matrix**; App. H | 🔴 Critical |
| **3** | Ten Admin Security Settings are inert — no code reads them | Ch. III › Development › Programming Environment › **module table** | 🟠 Major |
| **4** | MFA documented as optional; enforced for all roles | Ch. III › **Security Requirements** | 🟠 Major |
| **5** | Role enumeration omits OFW as a closed set | Ch. III › Design › Database Design › **Data Dictionary** | 🟠 Major |
| **6** | Acceptance criterion claims CSV report export | Ch. III › Testing › **Acceptance Testing** | 🟠 Major |
| **7** | Module inventory is 24 categories; repo has 35 | Ch. III › Development › **module table** + App. H | 🟠 Major |
| **8** | Cost table prices Render + Supabase | Ch. III › Planning › **Economic Feasibility › Cost Summary**; App. D | 🟠 Major |
| **9** | Topology and stack tables name Render / Supabase | Ch. III › Design › **Network Devices**; Development › **Database Stack**; Deployment | 🟠 Major |
| **10** | 120-minute session timeout never stated | Ch. III › Testing › **Acceptance Testing** + **Security Requirements**; App. J | 🟡 Minor |

### Suggested order of work

Start with **11** — it is the only finding a panel member can hit within five minutes of
testing, and it touches eight separate locations.

**1** and **5** are the same table cells — do them together. **8** and **9** share a
root cause — one pass. **7** must be applied twice, because the Chapter III module
table and Appendix H are identical copies. **3** is the only finding with no single
line of text, so start it last unless you have energy for it.

Numbering follows discovery order, not severity. The table above is sorted by severity.

---

## 1. Data dictionary invents a database type that does not exist
🔴 **Critical — the most falsifiable claim in the manuscript**

### Where

**Chapter III › Methodology › Design Phase › Database Design › Data Dictionary.**
Eight cells spread across six of the tables in that section:

| Table | Column marked `ENUM` |
|---|---|
| USER | `USER_ROLE` |
| CASES | `CASE_CLIENT_TYPE`, `CASE_STATUS` |
| CLIENTS | `CLNT_SEX` |
| REFERRALS | `REFR_STATUS`, `REFR_DECISION` |
| REFERRAL_COMMENTS | `COMM_VISIBILITY` |
| AUDIT_LOG | `AUDT_ACTION` |

**Find this:** `CASE_CLIENT_TYPE` — then search `ENUM` within that section.

### What it says

> `REFR_STATUS / Status / ENUM / PENDING, PROCESSING, COMPLETED, REJECTED, FOR COMPLIANCE`

### Code reality

A search of *all* of `database/migrations/` for `->enum(`, `CREATE TYPE`, and `pg_enum`
returns **zero matches**. The schema defines no PostgreSQL enum anywhere.

| Column | Actual definition |
|---|---|
| `users.role` | `string(50)` — `database/migrations/0001_01_01_000000_create_framework_tables.php:16` |
| `cases.client_type` | `string(20)` — `database/migrations/2026_06_01_000002_create_case_tables.php:38` |
| `cases.status` | `string(50)`, default `OPEN` — same file `:43` |
| `clients.sex` | `string(10)`, nullable — same file `:20` |
| `referrals.status` | `string(50)`, default `PENDING` — `database/migrations/2026_06_01_000003_create_referral_tables.php:19` |
| `referrals.decision` | `string(20)`, nullable — same file `:20` |
| `referral_comments.visibility` | `string(50)` — same file `:98` |
| `audit_logs.action` | `string(50)` **+ CHECK constraint** — `database/migrations/2026_06_01_000006_create_monitoring_tables.php:15`, constraint at `:35` |

The only value constraint in the database is a CHECK on a varchar:
`action::text IN ('CREATE','UPDATE','DELETE','LOGIN','LOGOUT','ARCHIVE','UNARCHIVE','PUBLISH')`
— not an enum.

Also note the `AUDT_ACTION` list itself is wrong: the manuscript omits `ARCHIVE`,
`UNARCHIVE`, and `PUBLISH`, and includes `VIEW`, which the constraint does not allow.

### Why it matters

A schema reviewer disproves this with one query. It overstates database-level type
safety across eight columns of the core data model.

### Fix

Change all eight Type cells from `ENUM` to `VARCHAR` with the real length. Move the
value lists out of the Range column into a note naming the enforcing layer — Eloquent
casts and `Rule::in()` request validation. Record the CHECK constraint verbatim for
`AUDT_ACTION`. If Chapter III claims database-level integrity, add a real
`CREATE TYPE` migration and say so.

---

## 2. SERVQUAL is claimed as a shipped feature; the code path was retired
🔴 **Critical — asserts a capability that no longer exists**

### Where

| Part · Section |
|---|
| **Ch. I › Purpose and Description** — one clause in the paragraph describing supporting functions |
| **Ch. I › Scope and Limitations › Scope** — one bullet in the "covers the following" list |
| **Bibliography** — one entry |
| **App. I** — all five questionnaires |

**Find this:** `SERVQUAL` (three occurrences outside Appendix I)

### What it says

> "…reporting and analytics, data export, SERVQUAL-based service feedback, and audit logging."

> "Supports the collection of client feedback through SERVQUAL-based survey forms following applicable assistance or referral processes."

### Code reality

SERVQUAL appears exactly three times in the whole manuscript — twice as a system
capability, once as a bibliography entry. Appendix I presents five instruments and
**all five are ISO/IEC 25010:2023 quality-characteristic checklists** (Case Manager,
Administrator, Agency Focal Person, IT Expert, OFW). There is no SERVQUAL instrument,
scale, or item set anywhere in the document.

The implementation was removed:
`database/migrations/2026_09_16_000002_drop_legacy_feedback_tables.php` drops the
legacy feedback tables. The live feedback surface is the generic ISO-aligned survey.

### Why it matters

A feature bullet asserts a capability the running system does not have. It also
contradicts the Purpose and Description section itself, which states the evaluation
model is ISO/IEC 25010:2023.

### Fix

Delete "SERVQUAL-based service feedback" from both locations; replace with something
consistent with the ISO/IEC 25010 framing already used two paragraphs later, such as
"AI-assisted inquiry support and structured system-quality feedback". Remove the
SERVQUAL bibliography entry — unless it is genuinely cited for related work, in which
case keep it and state explicitly that SERVQUAL was not used as an instrument.

---

## 3. Ten Admin Security Settings are inert — no code reads them
🟠 **Major — the highest-priority item in the audit**

### Where

**Chapter III › Methodology › Development Phase › Programming Environment › the
Programmer / Module / Function table** — the `System` module rows **"View System
Settings"** and **"Update System Settings"**. Duplicated in **Appendix H**.

There is no other location. The claim is spread wherever the settings themselves are
described, so search the whole document for the setting names rather than trusting one
spot.

**Find this:** `Password Minimum Length`

### Evidence

The ten keys, defined at `app/Services/SecuritySettingsService.php:13-22`:

`password_min_length` · `password_require_special` · `password_require_numbers` ·
`password_expiry_days` · `session_lifetime_minutes` · `max_login_attempts` ·
`lockout_duration_minutes` · `ip_whitelist_enabled` · `ip_whitelist_ips` ·
`two_factor_required`

They are **written and displayed, never enforced**:

- `SecuritySettingsService` is referenced in only two files — itself and `app/Http/Controllers/Admin/SecuritySettingsController.php`. There is no third consumer anywhere in `app/`, `config/`, `routes/`, or `resources/js/`.
- That controller performs **no runtime config write** — no `config()`, `Config::set()`, or `put()`. It only persists to the database, so nothing downstream can observe a change unless it reads the row.
- Three keys (`password_min_length`, `password_require_numbers`, `password_expiry_days`) do appear outside the service, but only in that controller and `resources/js/Pages/Admin/Security/Index.jsx` — the settings screen storing the value and echoing it back into its own form. Nothing in the enforcement path reads them.
- `app/Http/Requests/Auth/LoginRequest.php:35` validates login as `'password' => ['required', 'string']` — **no strength rule at all** — then only calls `Hash::check()` at `:49`. There is no login-time policy for these settings to configure.
- Password strength is enforced when a password is **set**, using rules hardcoded per call site and mutually inconsistent: `Password::min(8)->mixedCase()->numbers()->symbols()` in `app/Http/Requests/StoreAdminUserRequest.php:20` and `app/Http/Controllers/AdminUserController.php:169`, but `Password::min(8)->mixedCase()->numbers()` — **no symbols** — in `app/Http/Controllers/IntakeRegistrationController.php:34` and `app/Http/Controllers/TrackRegistrationController.php:39`, and `Rules\Password::defaults()` in the auth controllers. None of them read the settings.
- `config/session.php:35` reads `SESSION_LIFETIME` from the environment only, and no code writes it at runtime, so `session_lifetime_minutes` cannot take effect.
- `config/mfa.php` is likewise env-only, so `two_factor_required` and `max_login_attempts` cannot take effect.
- OTP attempt limits come from `self::MAX_ATTEMPTS` in `app/Services/OtpService.php:11,49`; MFA attempt limits from `config('mfa.max_attempts')` at `app/Http/Controllers/Auth/MfaChallengeController.php:62`. Neither consults the settings.
- `ip_whitelist_enabled` and `ip_whitelist_ips` appear **only** inside `SecuritySettingsService` itself. `bootstrap/app.php:76` registers the `ip.whitelist` alias to `App\Http\Middleware\IpWhitelist`, but that middleware is not fed by these settings.

### Fix — pick one. Do not do more than one.

1. Implement the settings so the documented behaviour is true, **or**
2. Remove the claim from the manuscript, **or**
3. State explicitly that the settings are persisted for future use but not yet enforced by the running application.

Option 3 is the honest minimum. **Leaving the manuscript asserting they work is the
defect itself.**

> **Verification note:** re-verified directly against the working tree, not carried
> forward. An earlier draft of this finding listed seven key names that **do not exist
> in the codebase** — `password_require_uppercase`, `password_require_lowercase`,
> `password_require_number`, `password_require_symbol`, `password_history_count`,
> `max_failed_login_attempts`, `account_lockout_minutes`, `session_idle_timeout` — all
> corrected above. The conclusion is unchanged and now rests on a direct reference scan.

---

## 4. MFA is documented as optional; the code enforces it for every role
🟠 **Major**

### Where

**Chapter III › Security Requirements** — the requirements table, `Authentication`
category. The same section's narrative and checklist are involved in finding 10.

**Find this:** `Multi-Factor Authentication (Optional)`

### What it says

> "Authentication / Multi-Factor Authentication (Optional) / Supports MFA for enhanced login security"

### Code reality

- `routes/auth.php:31-37` routes **every** login to `MfaChallengeController` after password verification.
- `bootstrap/app.php:67-68` registers `EnsureMfaSession` and `CheckMfaEnrolled` on the authenticated web stack; `bootstrap/app.php:79` applies them.
- `app/Http/Middleware/CheckMfaEnrolled.php:47-78` lets a non-enrolled user past **only** when the app is not in production *and* enrollment enforcement is disabled. Otherwise it redirects with "You must enable two-factor authentication before continuing."
- Its docblock states: "In production it returns true for every role (ADMIN, CASE_MANAGER, AGENCY, OFW), so the production override applies here automatically."
- `User::isInMfaEnforcedRole()` names all four roles.

### Why it matters

This **understates** a control the code enforces for every user in production — inside
the table whose entire purpose is to document the security posture. Understating a
control hurts a defense as much as overstating one. This is a *different* defect from
any login-method description error.

### Fix

Change "(Optional)" to "(Required)". Rewrite the description as: "TOTP-based MFA is
enforced for all authenticated users at login, with enrollment required before access."
State the non-production bypass explicitly rather than leaving it undocumented.

---

## 5. The role enumeration omits OFW as a closed set
🟠 **Major — same cells as finding 1; fix together**

### Where

**Chapter III › Design Phase › Database Design › Data Dictionary** — `USER` table,
`USER_ROLE` row.

**Find this:** `USER_ROLE`

### What it says

> `USER_ROLE / Role / ENUM / ADMIN, AGENCY, CASE_MANAGER`

### Code reality

- `database/migrations/0001_01_01_000000_create_framework_tables.php:16` — `role` is `string(50)`, not an enum.
- `app/Http/Middleware/CheckRole.php:13` authorizes with `in_array($request->user()->role, $roles)`; the enforced set is **CASE_MANAGER, AGENCY, ADMIN, OFW**.
- `role:OFW` guards a live authenticated route — `routes/web.php:445`.
- `app/Http/Controllers/OfwDashboardController.php` exists.
- `User::isInMfaEnforcedRole()` names all four roles.
- OFW is 50 of 94 respondents in **Ch. I › Methods › Respondents**, and has its own instrument in Appendix I.

### Why it matters

The cell presents three values as a **closed set**, and the fourth is both shipped and
the largest respondent group. A false enumeration, not an abbreviation.

### Fix

Range → `CASE_MANAGER, AGENCY, ADMIN, OFW`, to match `CheckRole`. Format cell →
`varchar(50)` (see finding 1).

---

## 6. An acceptance criterion claims CSV report export; none exists
🟠 **Major**

### Where

**Chapter III › Methodology › Testing Phase › Acceptance Testing** — the test scenario
row **"Report Export"**.

**Find this:** `Reports can be exported`

### What it says

> "Report Export / DMW / Administrator / Reports can be exported (PDF/CSV) / Reports exported successfully"

### Code reality

`app/Http/Controllers/ReportsController.php` implements two export paths —
`exportPdf()` streams a PDF, and `exportExcel()` sets a `.xlsx` content-disposition
filename. The reporting UI offers PDF and Excel.

CSV exists **only for audit-log export** — streamed at
`app/Http/Controllers/AuditLogController.php:163-183` via `fputcsv`, written through
`app/Services/Export/DataExportService.php`, wired at
`resources/js/Components/Reports/ExportButton.jsx:2`. There is no CSV path for reports.

### Why it matters

An acceptance criterion asserts verified capability, not a wish. "Demonstrate the CSV
export" produces an Excel file. *Not* the same as the original audit's "exports do not
exist" claim, which was false — exports exist across eleven routes.

### Fix

Change to "Reports can be exported (PDF/Excel)". If CSV is a genuine requirement, add
it as a separate not-yet-implemented row rather than an acceptance criterion.

---

## 7. The module inventory is 24 categories; the repository has 35
🟠 **Major**

### Where

| Part · Section |
|---|
| **Ch. III › Development Phase › Programming Environment › Programmer / Module / Function table** |
| **Appendix H — List of Modules** |

The two are byte-identical duplicates. **Fix both together.**

**Find this:** `List of Modules`

### Code reality

`docs/appendices/Appendix_N_List_of_Modules.md` carries **35**
`| [Programmer] | **…** |` category rows, 35 "No. of Points" sub-rows, and a total row
reading 35. The repository file is internally consistent — 35 = 35.

### Why it matters

Both manuscript copies understate the delivered inventory by 11 categories. Neither
carries the "No. of Points" sub-row or the "Total Number of Modules" row that the
repository version has.

### Fix

Replace both 24-category lists with the repository's 35-category inventory, including
the "No. of Points" sub-row and the "Total Number of Modules" row.

---

## 8 & 9. The manuscript prices and names a stack the project does not use
🟠 **Major — one root cause, fix in a single pass**

### Where

| Part · Section | What it does |
|---|---|
| **Ch. III › Planning Phase › Economic Feasibility › Cost Summary** | the cost table with per-month and per-year figures |
| **Ch. III › Development Phase › Testing Area** | restates the same monthly figures |
| **Ch. III › Design Phase › Network › Network Devices** | topology table naming the application server |
| **Ch. III › Development Phase › Database Stack** | names the database platform |
| **Ch. III › Deployment Phase › Database Server Environment** | names the hosting platform and application host |
| **Appendix D › Economic Feasibility** | repeats the cost summary |

**Find this:** `Render Starter Plan` · `Supabase Pro Plan` · `Render Application Server`

### What it says

> "Cloud Hosting (Render Starter Plan) ₱500 ₱6,000 | Database (Supabase Pro Plan) ₱1,400 ₱16,800 | Total ₱1,900 ₱22,800"

> "The low operational cost, estimated at approximately ₱1,900 per month"

### Code reality

- `config/database.php` uses a plain `pgsql` connection.
- `config/filesystems.php:63-79` puts case documents on a generic object-storage disk. Supabase survives **only** as a legacy env alias behind the canonical `STORAGE_*` vars.
- `.github/workflows/deploy.yml` deploys to a container service and contains the only provider-specific step in the pipeline.
- The arithmetic itself is sound: 500×12 = 6,000 · 1,400×12 = 16,800 · 1,900/month · 22,800/year. Appendix D agrees with the Cost Summary total.

### Important qualifier — do not overcorrect

Cloudinary is **real and in use**. `app/Services/CloudinaryAvatarService.php`,
`ProfileController`, `AdminAgencyController`, and
`database/seeders/AgencySeeder.php:15-23` all use it for avatars and agency logos.
Only *case documents* go to the object-storage disk. So the media-storage description
in the Development Phase needs **narrowing, not deletion** — and Cloudinary should
stay named in the Database Stack table, where it is accurate.

**Also:** the feasibility figures live in **Appendix D "Feasibility Analysis Summary"**,
not Appendix A. Appendix A is the signature/acknowledgement page.

### Fix

Restate the topology and platform rows in technology-and-capability terms — container
service, managed PostgreSQL, S3-compatible object storage — or add one sentence
identifying Render and Supabase as the original proposal-stage environment. For the
cost table, decide and state explicitly whether it documents the original proposal
estimate or the actual deployment; if the latter, restate the two component lines and
recompute the monthly and annual totals in both the Cost Summary table, the Testing
Area figures, and Appendix D. Follow the repo's own platform-neutrality rule
(`docs/DEPLOYMENT_GUIDE_v3.1.0.md` §1 and §12, `docs/PROJECT_RULES_v2.1.0.md`).

---

## 10. The 120-minute session timeout is never stated
🟡 **Minor — easiest fix in this list: add one number in two places**

### Where

| Part · Section | What it says |
|---|---|
| **Ch. III › Testing Phase › Acceptance Testing** | a test scenario named "Session Timeout" |
| **Ch. III › Security Requirements** — narrative before the table | "incorporates automatic session timeouts" |
| **Ch. III › Security Requirements** — requirements table, `Session Management` category | "Automatically terminates inactive sessions" |
| **Appendix J** — panelist required revisions | a panel action item on session timeout |

Four assertions, **none of which states a duration.**

**Find this:** `Session Timeout` · `automatic session timeouts`

### Code reality

- `config/session.php:35` — `'lifetime' => (int) env('SESSION_LIFETIME', 120)`
- `.env.example` and `.env.docker.example` — `SESSION_LIFETIME=120`
- `docs/DEPLOYMENT_GUIDE_v3.1.0.md` — 120 minutes
- `docs/SECURITY_REQUIREMENTS_v2.2.0.md` — "Configurable via `SESSION_LIFETIME` (default: 120 minutes)"

The absence is earned, not assumed: the whole document was searched for `timeout`,
`inactiv`, `idle`, `expir`, `log off`, `log out`, `auto-log`, `terminate`, and for any
duration expression. The only hits containing a time unit are "Record My Hours App" (a
competitor feature) and "Real-Time Dashboarding".

### Fix

Add the shipped value wherever the control is asserted:

> "Automatically terminates inactive sessions after **120 minutes (2 hours)** of inactivity, configurable via `SESSION_LIFETIME`"

Optionally quantify the Acceptance Testing expected-result cell so that UAT test is
executable as written.

---

## 11. The login walkthroughs omit the password and describe the wrong second factor
🔴 **Critical — the one finding that makes a deliverable unusable rather than merely inaccurate**

### Where

| Part · Section | What it says |
|---|---|
| **Ch. III › Documentations › User Guide › A. CASE MANAGER LOGIN** | Step 2 "Enter Registered Email" → Sign In; Step 3 "Verify OTP" — *"Check your email for the OTP / Enter the OTP in the system"* |
| **Ch. III › Documentations › User Guide › B. AGENCY LOGIN** | same two-step shape |
| **Ch. III › Documentations › User Guide › C. ADMINISTRATOR LOGIN** | same two-step shape |
| **Ch. III › Documentations › User Guide** — "OTP-Based Secure Access" | *"Implements One-Time Password (OTP) verification for all users to enhance security"* |
| **Ch. III › Documentations › User Guide › VI. TROUBLESHOOTING** | a fault entry for "OTP not received" |
| **List of Figures** | four figure captions — "OTP Verification Page (OFW / Case Manager / Agency / Administrator)" |
| **Ch. II › Comparative Matrix** and the **Appendix E** duplicate | "OTP when logging in" listed as a Bayanihan feature |
| **Ch. III › Development Phase module table** and **Appendix H** | an "OTP" module listed under Authentication |

**Find this:** `Verify OTP` — then `OTP when logging in`

### What it says

> "Step 2: Enter Registered Email / Input your registered email address / Click Sign In"
>
> "Step 3: Verify OTP / Check your email for the OTP / Enter the OTP in the system / Click Verify"

No step asks for a password. The second factor is described as a code delivered to the user's inbox.

### Code reality

Login is email **+ password**, then an authenticator-app **TOTP**:

- `routes/auth.php:27` — `POST login` → `AuthenticatedSessionController@store`
- `app/Http/Requests/Auth/LoginRequest.php:35` — `'password' => ['required', 'string']`; `:49` calls `Hash::check($this->string('password')->toString(), $user->password)`
- `routes/auth.php:31-32` — `GET login/mfa` → `MfaChallengeController@show`
- `routes/auth.php:33-34` — `POST login/mfa/totp` → `MfaChallengeController@totp`
- `routes/auth.php:35-36` — `POST login/mfa/recovery` → recovery codes

The real flow is **three** steps — email, password, TOTP — and the second factor comes from an authenticator app, not from email.

### Important qualifier — the OFW tracking OTP is correct

Do **not** remove every mention of OTP. The **OFW public case-tracking** flow genuinely does use an email-delivered code: "Step 5: Verify OTP (Security Step) / Check your email inbox for the One-Time Password (OTP)" in the User Guide introduction is accurate, matching the tracking controller's use of `debug_tracking_otp_enabled` (`app/Http/Controllers/TrackController.php:56`). The defect is confined to **staff login**.

### Why it matters

A reader following Chapter III would enter an email, then wait for a code that never arrives, having never been asked for the password that is actually required. It also presents email OTP as the security control in the same chapter that separately claims MFA is optional (finding 4) — the two errors compound, and a panel member testing the documented flow would read this as a working login.

### Fix

Rewrite all three staff login walkthroughs as three steps — registered email, password, then authenticator-app TOTP — and document the recovery-code path. Correct the "OTP-Based Secure Access" claim to name TOTP. Delete or rewrite the "OTP not received" troubleshooting entry as an MFA-code entry. Recaption the four figures so only the OFW tracking figure keeps "OTP Verification Page". In the Comparative Matrix and Appendix E, replace "OTP when logging in" with TOTP-based MFA. In the module tables, rename the Authentication module's "OTP" row to reflect TOTP MFA — applying that to **both** Chapter III and Appendix H.

---

# Claims checked and CLEARED — do not "fix" these

| Claim | Verdict |
|---|---|
| Exports exist | **CLEARED.** 11 export routes in `routes/web.php`; XLSX in five controllers plus `app/Services/Export/DataExportService.php`; CSV streamed at `app/Http/Controllers/AuditLogController.php:163-183`. The original "exports do not exist" was false. |
| Webhook endpoint undocumented | **CLEARED.** `POST /api/webhooks/resend` exists at `routes/api.php:20-26`, but the manuscript has **zero** occurrences of "webhook", "web hook", or "Resend". It never claims it. |
| Sample-size arithmetic (144 / 94) | **CLEARED.** Population 5+5+24+100+10 = 144; sample 5+5+24+50+10 = 94; all five percentages correct against the denominator declared in **Ch. I › Methods**, summing to 100.00%. Verified four times. |
| Case Closure Restriction | **CLEARED.** Implemented — `app/Services/CaseService.php` `canClose()`, enforced in the close and update paths. |
| Public chatbot endpoint | **CLEARED.** `POST /chatbot/message` is intentionally public (`routes/web.php:440-442`) and `AppLayout.jsx` does not render the widget, so denying internal roles is UI-consistent. |
| Table numbering | **CLEARED.** 31 `SEQ Table` field codes for Tables 1–31 — the sequence is intact. |
| Document encoding | **CLEARED.** Raw U+FFFD count is **0** across the whole document. Any mojibake is console rendering of the ✓/✗ glyphs (U+2713/2714/2717), not a defect in the file. |

# ⛔ Retractions — two old "findings" that would damage your manuscript

**"The Related Studies column is empty." FALSE. Do not delete the column.**
All six Chapter II competitor tables carry the literal header text, followed in the
same header run by "Features", "Limitations", "Platform Details", "Support" and
"Social Impact" — an unambiguous six-cell header row. There are six tables, not four.
A possible *mislabel* exists — the column holding "System Name / Organization / URL"
appears to be headed "Related Studies" — but the cell-to-column mapping cannot be
confirmed from a lossy text extraction, so it is not filed as a defect. **Confirm the
mapping in Word before touching it.**

**"Centre vs Center." Real, but the old fix would rename a partner agency.**
Only five lines are the organisation: change those to "Centre". **Do NOT touch**
"Law Center Inc." (a partner agency's proper name, in Scope and Limitations),
"Civic Center Twp" (a Pakistani district), ordinary prose uses of "center", or the
verb "centers on" in the Purpose and Description.

---

# Known to need a human decision — not defects

1. **Cost table: original proposal estimate or actual deployment?** (findings 8/9) The inconsistency is objectively verifiable; whether to restate a proposal-stage projection is yours to call.
2. **The Security Requirements narrative inverts the encryption claim.** It says the system works by "transforming personally identifiable information into readable information" — eleven words after the same paragraph correctly says files are made "unreadable". **Ch. III › Security Requirements.**
3. **Confidentiality evidence.** Twelve columns claim a confidentiality capability; a visual pass on the rendered figures is required to confirm the evidence exists.

# Still verifying

Criticals and Majors from the earlier audit are under adversarial re-verification.
Known leads, **not yet confirmed — do not act on these yet**:

- ~~**Login method**~~ — **resolved, filed as finding 11 above.** The User Guide's three staff login walkthroughs describe email + email OTP with no password step; the code is email + password + authenticator TOTP.
- **Comparative Matrix percentages** wrong in 3 of 7 columns — E-Migrate 70.83 vs 66.67, DOE 37.50 vs 41.67, GLMIS 29.17 vs 33.33. Bayanihan 24/24, ELMIS 14/14, FWO 13/13, MRC 9/9 are correct. **Ch. II › Comparative Matrix** and the **Appendix E** duplicate.
- **ERD foreign-key direction** may contradict the data dictionary. **Ch. III › Design Phase › Database Design.**

---

# Two constraints when editing

**1. Work from the rendered document, not a text dump.** Never propagate Word field
codes (`SEQ`, `PAGEREF`) left unresolved, words split across lines, or image-anchor
coordinate runs. One known artefact reads `centercenter` (American) — `centrecenter`
has zero occurrences.

**2. Panel and adviser records are evidence.** Recorded panelist mandates (Appendix J),
hearing outcomes, and adviser acceptance lines (Appendix C) must not be merged,
renumbered, reworded, or deleted to make a table tidier. Where two panelists
independently requested the same revision, **annotate** — do not consolidate. The
panel table records **20 distinct mandates** across three panelists; they are not
duplicates.

---

## Superseded files

Earlier audit files were removed from `docs/` and archived; nothing is lost.

| File | Status |
|---|---|
| `SUPERSEDED_MANUSCRIPT_MISSING_8.md` (archived, `%TEMP%\opencode\lane_results\`) | Its two live findings are now #3 and #10 here; its retracted items are in Retractions above; its parked items were out of scope. |
| `SUPERSEDED_MANUSCRIPT_CONSISTENCY_CHECKLIST_Rev2.txt` (archived; byte-identical master at `%TEMP%\opencode\checklist_MASTER_COPY.txt`) | Revision 2 audit trail — 46 findings, mostly manuscript-internal. |
| `SUPERSEDED_MANUSCRIPT_FIX_BATCHES.md` (archived, `%TEMP%\opencode\lane_results\`) | A section index over a lost Revision 4. Lists 8 missing findings but carries no per-finding fixes. |
