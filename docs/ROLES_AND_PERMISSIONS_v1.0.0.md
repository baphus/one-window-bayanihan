# Roles and Permissions

> **Version:** 1.1.0 | **Updated:** 2026-10-03 | **Status:** Verified against source code
> **Source:** `app/Models/User.php:98-115`, `app/Http/Middleware/CheckRole.php`,
> `app/Http/Middleware/IpWhitelist.php`, `config/auth.php:64-77`,
> `config/mfa.php`, `routes/web.php`, `routes/auth.php`, `routes/api.php`,
> `app/Http/Controllers/Auth/MfaChallengeController.php`,
> `app/Services/OtpService.php`, `bootstrap/app.php:63-80,120-133`,
> `database/migrations/0001_01_01_000000_create_framework_tables.php`

## 1. Role Vocabulary (exact, closed)

Roles are plain strings in the `users.role` column — a `string(50)`, **not** a
database or PHP enum (`database/migrations/0001_01_01_000000_create_framework_tables.php:16`;
`app/Enums/` contains only `AuditAction` and `AuditModule`, so there is no role enum to
discover). There are exactly four — no more, no fewer — with predicate helpers on the model
(`User.php:98-115`):

| Slug | Helper | Who |
|------|--------|-----|
| `ADMIN` | `isAdmin()` (`User.php:98-100`) | System administrators |
| `CASE_MANAGER` | `isCaseManager()` (`User.php:103-105`) | DMW staff — own the case file |
| `AGENCY` | `isAgency()` (`User.php:108-110`) | Partner-agency focal staff |
| `OFW` | `isOfw()` (`User.php:113-115`) | Overseas Filipino Workers (portal only) |

There is no permissions table, no role hierarchy, and no role inheritance:
every authorization decision is `role` middleware on the route plus
per-controller scoping (lane isolation, §4). Guards named in prose elsewhere
(e.g. "staff", "manager", "focal") are not code — use only the four slugs above.

## 2. Enforcement Primitive: `role` Middleware

`app/Http/Middleware/CheckRole.php:11-18`:

```php
if (! $request->user() || ! in_array($request->user()->role, $roles)) {
    abort(403, 'Unauthorized access.');
}
```

Strict `in_array` against the route's allow-list; anything else — including a
missing user — aborts **403**, never 401 (authentication failures are handled
separately — §7). The middleware is aliased as `role` (`bootstrap/app.php:75`)
and always runs inside the `auth`/`verified` web group or an explicit `auth`
group, after `CheckUserActive`, `EnsureMfaSession`, `CheckMfaEnrolled`, and
`HandleInertiaRequests` (`bootstrap/app.php:63-72`).

## 3. Route-Group Matrix (`routes/web.php`, 455 lines)

Every group below inherits `auth` (+ `verified` where noted). Line references
are to `routes/web.php`.

`role:` allow-list occurrences in `routes/web.php` (455 lines), counted on
2026-10-03: `role:CASE_MANAGER` ×8, `role:ADMIN` ×3, `role:AGENCY` ×2,
`role:OFW` ×1.

| Lines | Group | Allowed roles | What it exposes |
|-------|-------|---------------|-----------------|
| 62-316 | `auth` + `verified` mega-group | any authenticated, verified user | dashboard, profile + MFA self-service (69-73), referrals (76-106), reports (109-111), notifications (113-119), onboarding (235-243) |
| 122-155 | cases management | `CASE_MANAGER,ADMIN` | case CRUD, drafts, intake queue, publish/archive/restore, trash, stakeholders, audit-log export (controller additionally enforces `isAdmin`) |
| 161-163 | audit-log viewer | `CASE_MANAGER,ADMIN,AGENCY` | `/audit-logs`; rows scoped per role in `AuditLogController::buildFilteredQuery` (admin all, manager own cases, agency own referrals + parent cases) |
| 166-167 | case detail | `CASE_MANAGER,ADMIN,AGENCY` | `cases.show` — agency access authorized in the controller (active referral required) |
| 169-173 | case documents (read) | `CASE_MANAGER,ADMIN,AGENCY` | document index/show/download |
| 175-178 | case documents (write) | `CASE_MANAGER,ADMIN` | document store/destroy — agency has no write surface |
| 181-188 | clients | `CASE_MANAGER,ADMIN,AGENCY` | client index/show, avatar store/destroy; per-role authorization in the controller |
| 190-203 | agency lane | `AGENCY` only | client-request lifecycle (`store`, `sendMessage`, `complete`, `cancel`, `reopen`, `issue`, `reissue` — several with `agency-client-*` throttles) + own service catalog (`agency.services.*`) |
| 205-209 | client-request visibility | `CASE_MANAGER,ADMIN,AGENCY` | request index, agency-attachment download, access-link revoke |
| 212-220 | survey form builder | `AGENCY` only | `survey.forms.*` CRUD + activate |
| 223-226 | survey responses | `CASE_MANAGER,ADMIN,AGENCY` | `survey.responses.index/show` |
| 229-232 | agency detail | `ADMIN,CASE_MANAGER` **+ `ip.whitelist`** | read-only for non-admin; the only non-`ADMIN`-only route that still requires IP whitelisting |
| 245-315 | `admin` prefix | `ADMIN` **+ `ip.whitelist`** | agencies, services, users (+ invites, email-change OTP, MFA reset), system settings (+ chatbot reindex), case taxonomies, data export, `system.*` (logs, maintenance, security, active sessions, email logs) |
| 318-321 | overdue referrals | `ADMIN,CASE_MANAGER,AGENCY` (`auth` only, no `verified`) | index + send-reminders |
| 432-438 | session API (`/api/*` in web.php) | `auth` + `verified` + `throttle:api-global` | client search / email-check / show for the case form |
| 445-453 | `my-cases` portal (`ofw.*`) | `OFW` only | dashboard, notifications (+ mark-read), agency milestones, profile edit/update, case show — 7 routes, the OFW's entire surface |

Public (no auth): survey token submit (53-58), home (60), partners (323-347),
contact + legal (349-363), intake wizard (366-381), tracking portal (383-413),
help center (415-429), chatbot message (440-442, `turnstile.session` +
`throttle:chatbot`). Unauthenticated API (`routes/api.php`, **3 routes**:
`readyz` probe at `routes/api.php:12-14`, `csp/report` at `:17-18`,
`webhooks/resend` at `:24-26` — Svix-verified in-controller, deliberately
outside session/CSRF/MFA). The five former `/api/address/*` PSGC lookups no
longer exist: the address dataset is a generated file read directly by the
frontend (`resources/js/data/philippine-addresses.ts`, regenerate with
`npm run addresses:sync`).

Auth routes (`routes/auth.php`, 118 lines, **23 routes** — 13 guest + 10
authenticated): guest login
(`turnstile` + `throttle:login`), MFA challenge — `show`/`totp`/`recovery`/
`cancel` behind `mfa.pending` (`MfaChallengeController`, `routes/auth.php:31-38`;
there is no `LoginOtpController` — do not reference one), invite registration,
password reset/confirm, email verification, authenticated email-change OTP
(`throttle:otp`), logout (writes a `LOGOUT` audit row, clears MFA pending
state, invalidates the session).

## 4. Lane Isolation

Middleware decides *whether* a role may reach a controller; the controller
decides *which rows* it may see:

- **Agency lane:** agencies see only referrals assigned to their agency
  (`agcy_id` filter) and may view a case only with an active referral on it;
  they cannot see other agencies' referrals or milestones on shared cases.
  Agency-to-agency coordination happens through the per-referral message thread
  (`ReferralMessageController::index/store/markRead`, `web.php:98-100`).
- **Audit scope:** `AuditLogController::buildFilteredQuery` narrows rows to
  admin-all / manager-own-cases / agency-own-referrals-plus-parents (§3, 161).
- **OFW isolation (§5)** is the limiting case of this pattern: a wholly
  separate route prefix and controllers, not merely row filters.

## 5. OFW Isolation

`OFW` accounts are confined to the `my-cases` prefix (`web.php:445-453`,
middleware `auth` + `role:OFW`, names `ofw.*`): dashboard, notifications,
agency milestones for their own cases, profile, case show.

Role-gated paths reject them: `/cases`, `/clients`, and `/admin/*` each carry a
`role:` allow-list that omits `OFW`, so `CheckRole` aborts 403 (A2). But `/referrals`
and `/reports` are **not** role-gated 
—
 they sit in the `auth`+`verified` group
(line 62) with no `role:` middleware, so `CheckRole` never runs and any authenticated
verified user reaches them, OFW included. That matches 
§
3. OFW accounts are created
with `email_verified_at` already set (`IntakeRegistrationController.php:70`), so `verified`
does not block them either. `ReferralService::getReferrals` scopes rows for `AGENCY` only
(`app/Services/ReferralService.php:270-277`) and has no OFW branch, so what OFW sees at
`/referrals` is unscoped.
Conversely, staff roles never match `role:OFW` and cannot enter the portal.
Outside production, OFW is excluded from MFA enrollment enforcement (the
`enrollment_enforced_roles` default is `ADMIN,CASE_MANAGER,AGENCY`,
`config/mfa.php:30-33`). **In production that exclusion does not apply** —
`User::isInMfaEnforcedRole()` (`app/Models/User.php:118-131`) enforces MFA for
every non-empty role, OFW included.

## 6. Admin IP Whitelist

Second gate on every `admin` route plus the agency-detail route (§3, 229/245),
via the `ip.whitelist` alias (`bootstrap/app.php:76` →
`App\Http\Middleware\IpWhitelist`):

- Disabled by default: `config/auth.php:74-77` reads
  `AUTH_IP_WHITELIST_ENABLED` (default `false`) and
  `AUTH_IP_WHITELIST_ADDRESSES` (default `127.0.0.1`). When disabled, the
  middleware passes through (`IpWhitelist.php:18-20`) — the `role:ADMIN` check
  still applies.
- When enabled, matching is exact-IP or CIDR (`IpWhitelist.php:34-53`,
  `ip2long` + mask); a non-matching IP aborts **403** (`IpWhitelist.php:31`).
- Operational note: enabling it without adding the deployment's egress/NAT
  addresses locks every admin out — verify the address list before flipping
  `AUTH_IP_WHITELIST_ENABLED=true`.

## 7. MFA & OTP: Two Mechanisms, Different Jobs

| Mechanism | Where configured | Policy | Applies to |
|-----------|-----------------|--------|------------|
| TOTP MFA challenge (authenticator app + recovery codes) | `config/mfa.php:4-7`; `MfaChallengeController`; `mfa.pending` alias (`bootstrap/app.php:79`) | `pending_ttl` 300 s, `max_attempts` 5, `replay_ttl` 120 s; self-service enroll/verify/disable/regenerate at `profile/mfa/*` (`web.php:69-73`) | login for roles in `config('mfa.enrollment_enforced_roles')` — default `ADMIN,CASE_MANAGER,AGENCY` (`config/mfa.php:30-33`) — and for **all** roles incl. `OFW` when `APP_ENV=production` (`User::isInMfaEnforcedRole()`, `app/Models/User.php:118-131`); enforced pre-access by `EnsureMfaSession` + `CheckMfaEnrolled` (`bootstrap/app.php:67-68`) |
| Email OTP (6-digit) | `App\Services\OtpService:11-13` | `TTL_MINUTES` 5, `MAX_ATTEMPTS` 5, cache-backed with per-purpose attempt counters | email-change (`auth.php:88-93`, `throttle:otp`), public intake verification, tracking-portal OTP — never login |

Admins can reset a user's MFA (`admin.users.reset-mfa`, `web.php:269`).

## 8. Denied-Access Behavior (`bootstrap/app.php:120-133`)

| Failure | Web (Inertia) response | API/JSON response |
|---------|----------------------|-------------------|
| 403 `CheckRole` / `IpWhitelist` (`AccessDeniedHttpException`) | `Errors/Forbidden` page, status 403 (`app.php:120-126`) | `{ message: 'Forbidden.' }`, 403 |
| 401 unauthenticated (`AuthenticationException`) | redirect to `route('login')` (`app.php:127-133`) | `{ message: 'Unauthenticated.' }`, 401 |
| 404 unknown route | `Errors/NotFound`, 404 (`app.php:103-109`) | `{ message: 'Resource not found.' }`, 404 |
| 429 rate-limit | `Errors/TooManyRequests`, 429 (`app.php:134-140`) | `{ message: 'Too many requests…' }`, 429 |

Deactivated users are rejected earlier by `CheckUserActive`
(`bootstrap/app.php:66`) before any role check runs.

---

## 9. Changelog

| Version | Date | Change |
|---|---|---|
| 1.1.0 | 2026-10-03 | Re-verified against source. §1: recorded that `users.role` is `string(50)` (`0001_01_01_000000_create_framework_tables.php:16`) and that `app/Enums/` holds only `AuditAction`/`AuditModule` — there is no role enum. §3: added the `role:` occurrence counts (CASE_MANAGER ×8, ADMIN ×3, AGENCY ×2, OFW ×1); corrected `routes/api.php` from "8 routes … 5 address lookups" to the 3 routes that exist (`readyz`, `csp/report`, `webhooks/resend`) and noted the removed `/api/address/*` endpoints; corrected `routes/auth.php` from "~19 routes" to 23 (13 guest + 10 authenticated, `routes/auth.php` is 118 lines) and cited the MFA challenge block at `:31-38`. §5/§7: corrected MFA enrollment enforcement — roles come from `config/mfa.php:30-33`, not the comment at `:22-25`, and production enforces MFA for every role including `OFW` (`app/Models/User.php:118-131`). |
| 1.0.0 | 2026-09-15 | Initial verified release: closed four-slug role vocabulary, `CheckRole` primitive, route-group matrix, lane isolation, OFW isolation, admin IP whitelist, MFA vs email-OTP split, denied-access rendering. |
