# Roles and Permissions

> **Version:** 1.2.0 | **Updated:** 2026-11-01 | **Status:** CURRENT; source-reconciled (filename retained)
> **Sources:** `routes/web.php`, `routes/auth.php`, `routes/api.php`, `bootstrap/app.php`, `app/Enums/UserRole.php`, `app/Models/User.php`, `app/Http/Middleware/CheckRole.php`, `config/mfa.php`, and the controller/service checks cited below.

## 1. Role vocabulary

`users.role` is a plain `string(50)` database column, not a database enum or an enum-cast model attribute. `App\Enums\UserRole` provides the four supported backing values and helpers; `User::roleEnum()` and `hasRole()` bridge the string column to that enum.

| Value | User helper | Audience |
|---|---|---|
| `ADMIN` | `isAdmin()` | System administrators |
| `CASE_MANAGER` | `isCaseManager()` | DMW case-management staff |
| `AGENCY` | `isAgency()` | Partner-agency focal staff |
| `OFW` | `isOfw()` | Citizens, including next-of-kin account holders |

There is no permissions table or inherited role hierarchy. Middleware determines route eligibility; Form Requests, controllers, and services enforce additional role, record, state, and ownership conditions.

## 2. Route-role enforcement

`CheckRole::handle()` reads the authenticated user's role and compares it strictly with the route allow-list:

```php
$role = $request->user()?->role;

if ($role === null || ! in_array($role, $roles, true)) {
    abort(403, 'Unauthorized access.');
}
```

Authentication is separate from role authorization. Global/web middleware also handles active accounts, MFA state/enrollment, session context, security headers, and Inertia props. Inspect current route definitions rather than historical route counts or line numbers.

## 3. Route surfaces

| Surface | Route eligibility | Additional conditions |
|---|---|---|
| Case list, review queue, lifecycle, case exports, trash | `CASE_MANAGER,ADMIN` | State guards; owner-scoped Case Manager trash/restore |
| Case create/store | `CASE_MANAGER,ADMIN,OFW` | `StoreCaseRequest` and `CaseService` resolve authorized filing client |
| Citizen intake | `OFW`, under `my-cases` | Linked client and consent; no active-case duplicate guard |
| Internal case detail | `CASE_MANAGER,ADMIN,AGENCY` | Agency needs an active referral |
| Case documents: read | `CASE_MANAGER,ADMIN,AGENCY` | Document/case visibility checks |
| Case documents: write | `CASE_MANAGER,ADMIN` | Record authorization and upload validation |
| Client directory and exports | `CASE_MANAGER,ADMIN,AGENCY` | Agency lane checks |
| Broad client picker search/email-check/show | `CASE_MANAGER,ADMIN` | Authenticated/verified, throttled; email-only normalized duplicate-client check |
| Linked-OFW picker | `OFW` | Only clients linked to the filer's own next-of-kin record |
| Referral list/create page/detail/status/milestones/list exports | `CASE_MANAGER,ADMIN,AGENCY` | Creation and mutation have additional request/service rules; create-page eligibility is not permission to submit a referral |
| Referral deletion | `CASE_MANAGER,ADMIN` | Record/state checks |
| Referral comments/replies | Authenticated/verified | Controller/service access checks; not unrestricted citizen collaboration |
| Agency-pair message read/write/read-marker | Authenticated/verified | `ReferralMessageService::assertCanAccessThread()` requires active participating agency; excludes Case Managers and Administrators |
| Referral attachment mutation | `CASE_MANAGER,ADMIN,AGENCY` | Authorized referral; removal restricted to uploader; agency UI omits mutation controls |
| Reports, print, PDF, Excel | `CASE_MANAGER,ADMIN,AGENCY` | Agency-scoped figures, export guards and audit; no report CSV route |
| Full audit-log index/actors/export | `ADMIN` | Export requires bounded explicit date range and row cap |
| Operational activity and case/referral activity | `CASE_MANAGER,AGENCY,ADMIN` | `ActivityScope`, `ActivityVisibility`, and record checks |
| Client-request management and issue/reissue | `AGENCY` | Receiving agency, lifecycle and delivery limits |
| Client-request list/review/download and access revoke | `ADMIN,AGENCY` | Record checks; Case Manager is not in this route group |
| Agency catalogue and survey form builder | `AGENCY` | Own agency |
| Survey response views | `CASE_MANAGER,ADMIN,AGENCY` | Agency response scoping |
| Agency detail | `ADMIN,CASE_MANAGER` | Case Manager read-only |
| Administration | `ADMIN` | Users/invitations, agencies/services, taxonomies, settings, maintenance/security; self/last-admin protections |
| Overdue list and reminder route | `ADMIN,CASE_MANAGER,AGENCY` | Agency may view own lane but `sendReminders()` rejects agency senders |
| Citizen portal | `OFW` | Own case/client membership and request access |

Some read/write endpoints deliberately rely on deeper authorization rather than dedicated `role:` middleware. Missing middleware is not evidence of unrestricted reads; trace the controller and service.

### Public and authentication routes

Public routes include home, partners, contact/legal pages, audience-filtered help, chatbot message, token survey submission, and tracker/email-code access. Citizen filing requires an account. `routes/auth.php` connects live citizen registration to profile capture and email-code review; it is not an orphan registration screen. Staff registration is invitation-based.

`routes/api.php` defines readiness, CSP reporting, mail-delivery webhook, and registration email-availability endpoints with endpoint-specific protection. API-looking session routes also live in `routes/web.php`; their file location is not an authentication contract.

Administrators have a Monitoring group — system health, audit logs, security log, email logs, and failed jobs with retry — alongside maintenance and security settings. The former application-log download/view and resend screens, and the full-workbook export, are retired. `IpWhitelist` is no longer application middleware; this document does not promise admin IP filtering.

## 4. Record isolation and lifecycle

- **Case Manager:** normal case access is office-wide. `CaseController::authorizeCaseAccess()` does not require matching `user_id`. Trash listing/restoration use `CaseService::scopeToTrashOwner()` and are owner-scoped.
- **Agency:** referrals and reporting follow `agcy_id`; an internal case requires an active referral. Document access and client reads apply their own lane checks.
- **Agency messages:** `ReferralMessageService` requires an active agency account, a different target agency, and a referral for the actor's agency on the same case. Read, send, and mark-read all enforce participation. Pair conversations remain private from staff oversight roles.
- **Citizen:** portal controllers and services check client/case association; report/referral staff lists and broad client-picker endpoints reject OFW roles. Client-visible case queries exclude legacy drafts but include pending-review and archived cases.
- **Review:** new cases are `PENDING_REVIEW`; approval sets `OPEN` and reviewer/time without replacing the filer. Rejection sets `REJECTED` with a reason and client notification. The current method does not call soft-delete. No draft editor/delete/publish workflow exists.

## 5. Audit versus operational activity

Full `/audit-logs` access and CSV export are **ADMIN-only**. Do not describe Case Managers or agencies as full audit-log viewers.

Separate `/activity` reads use `ActivityScope` and `ActivityVisibility`: the Case Manager aggregate feed follows their authored cases and related entities; the agency aggregate feed follows its referrals. Per-case/per-referral activity endpoints apply their own record authorization and visibility filters. Thus office-wide case access, aggregate activity scope, and trash ownership are distinct contracts.

## 6. MFA and email verification codes

| Mechanism | Contract |
|---|---|
| Authenticator MFA and recovery codes | Email/password login followed by MFA where enabled/required; pending challenge default 300 seconds, maximum 5 attempts, replay window 120 seconds |
| Production MFA | `User::isInMfaEnforcedRole()` requires MFA for every non-empty role, including `OFW`; enrollment flags cannot bypass production enforcement; `MfaController::disable()` blocks disabling in production |
| Non-production MFA | `config/mfa.php` controls enrollment enforcement; default roles come from `UserRole::staffValues()`; citizen enrollment is supported but not in the default enforced set |
| Email codes | Citizen registration review, email changes, tracking/document-request access; five-minute, single-use verification through `OtpService`, not the password-login second factor |

Administrators may reset a user's MFA under the protected user-administration action. Recovery codes provide a self-service fallback. Account/credential changes can invalidate sessions; deactivated users are refused on subsequent requests.

## 7. Denied access

`CheckRole` returns 403 for a missing/disallowed role. Unauthenticated protected requests are handled separately by authentication middleware. Inertia exception rendering provides forbidden, not-found, rate-limit, and other error pages; JSON requests receive corresponding error responses. Individual ownership-sensitive endpoints may intentionally return 404 rather than reveal that another person's record exists.

## 8. Related documentation

- [Plain-language roles and access](system-analysis-and-design/06-user-roles-and-access-control.md)
- [Operational scope](system-analysis-and-design/05-operational-scope-and-functionalities.md)
- [Project rules](PROJECT_RULES_v2.1.0.md)

## 9. Changelog

| Version | Date | Change |
|---|---|---|
| 1.2.0 | 2026-11-01 | Reconciled current role enum/string storage, staff route gates, office-wide case access versus owner-scoped trash, separate activity/audit surfaces, message-service authorization, agency attachment backend/UI distinction, live registration, retired screens, review lifecycle, and production/non-production MFA. Removed volatile route/line counts; filename retained. |
| 1.1.0 | 2026-10-03 | Re-verified against source. §1: recorded that `users.role` is `string(50)` (`0001_01_01_000000_create_framework_tables.php:16`) and that `app/Enums/` holds only `AuditAction`/`AuditModule` — there is no role enum. §3: added the `role:` occurrence counts (CASE_MANAGER ×8, ADMIN ×3, AGENCY ×2, OFW ×1); corrected `routes/api.php` from "8 routes … 5 address lookups" to the 3 routes that exist (`readyz`, `csp/report`, `webhooks/resend`) and noted the removed `/api/address/*` endpoints; corrected `routes/auth.php` from "~19 routes" to 23 (13 guest + 10 authenticated, `routes/auth.php` is 118 lines) and cited the MFA challenge block at `:31-38`. §5/§7: corrected MFA enrollment enforcement — roles come from `config/mfa.php:30-33`, not the comment at `:22-25`, and production enforces MFA for every role including `OFW` (`User::isInMfaEnforcedRole()`, `app/Models/User.php:118-131`). |
| 1.0.0 | 2026-09-15 | Initial verified release: closed four-slug role vocabulary, `CheckRole` primitive, route-group matrix, lane isolation, OFW isolation, admin IP whitelist, MFA vs email-OTP split, denied-access rendering. |

Historical changelog statements describe their dated revisions; current sections above supersede them.
