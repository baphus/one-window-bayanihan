# Agency Focal Owns Service Assignment — Implementation Plan

**Goal:** Remove the redundant service/requirement sections from the referral flow and make the agency focal the sole owner of assigning which services apply to a case referral.

**Problem:** A referral is created with services pre-selected by the case manager (`Create.jsx` Step 3). `createReferral()` then writes the legacy `required_services` text, syncs the `referral_services` pivot, and snapshots global requirements into `referral_service_requirements`. On the Show page, the "Required Services" section renders one expandable `ServiceCard` per attached service — the "EDSP (Emergency Shelter Assistance) (2 requirements)" and "Calamity Assistance (2 requirements)" blocks the user sees. The agency focal already has full add/remove/edit CRUD on that section (`ServiceAddDropdown` + `ServiceCard`), so the creation-time pre-assignment is redundant work that belongs to the focal.

**Architecture:** Decouple service assignment from referral creation. Referrals are created with case + agency + remarks + documents only. The Show page "Required Services" section becomes the single assignment surface, owned by the AGENCY role. The legacy `required_services` text column is kept as a derived cache (recomputed from the pivot on every service mutation) so the ~15 existing read sites (notifications, emails, reports, dashboard, exports, PDFs, Admin Agency Show, audit formatter) keep working without a migration.

**Tech Stack:** Laravel 13, Inertia (React 18), PostgreSQL 17, PHP 8.4.

## Global Constraints

- Follow existing coding patterns (AGENTS.md).
- Pivot (`referral_services`) + `referral_service_requirements` remain the canonical source of truth; `referrals.required_services` is a display fallback only (per `docs/DB_SCHEMA_DEAD_TABLE_AUDIT_2026-09-16.md:59`).
- Service/requirement mutations stay AGENCY-only (existing inline `role !== 'AGENCY'` guards in `ReferralController`).
- Run `vendor/bin/pint` and the affected tests after changes.

## Decisions Needed (reviewer)

| # | Decision | Recommendation |
|---|---|---|
| D1 | Should the "Required Services" section on the Show page stay visible to non-agency roles? | **Keep visible, read-only**, with an "awaiting agency assignment" empty state — case managers need transparency on what the focal is doing. |
| D2 | Legacy `required_services` column handling | **Keep as derived cache**, recomputed from the pivot on add/remove service. Full migration of all reads to the pivot is a larger follow-up, not part of this change. |
| D3 | Notify the agency focal when a referral arrives with no services assigned | **Yes** — the referral-created notification to the agency should say services are pending assignment, so the focal knows to act. |

## File Map

| File | Action |
|---|---|
| `app/Http/Requests/StoreReferralRequest.php` | Modify — drop `required_services` + `services` validation |
| `app/Services/ReferralService.php` | Modify — stop persisting services at creation; add `syncRequiredServicesText()`; call it from service-mutation paths |
| `app/Http/Controllers/ReferralController.php` | Modify — ensure `addService()`/`removeService()` keep the text column in sync |
| `resources/js/Pages/Referral/Create.jsx` | Modify — remove Step 3 service selection + requirements preview; rename step to "Details" |
| `resources/js/Pages/Referral/Show.jsx` | Modify — empty-state copy for the "Required Services" section |
| `app/Notifications/ReferralCreated.php`, `PeerReferralCreated.php` | Modify — "services pending assignment" copy when none assigned |
| `tests/Feature/ReferralServiceTest.php` (or nearest existing referral service test) | Modify/add — creation without services; add/remove service keeps text synced |
| `docs/DB_SCHEMA_DEAD_TABLE_AUDIT_2026-09-16.md` | Modify — note `required_services` is now a derived cache |

---

## Task 1: Stop persisting services at referral creation

**Files:**
- Modify: `app/Http/Requests/StoreReferralRequest.php:22-24`
- Modify: `app/Services/ReferralService.php:72-97` (`createReferral()`)

- [ ] **Step 1: Remove `required_services` and `services` from `StoreReferralRequest::rules()`**

Delete lines 22-24:

```php
'required_services' => ['nullable', 'string', 'max:5000'],
'services' => ['nullable', 'array'],
'services.*' => ['string', 'max:255'],
```

The request still authorizes only `isAdmin() || isCaseManager()` (line 14) — unchanged.

- [ ] **Step 2: Strip service persistence from `ReferralService::createReferral()`**

Remove the `required_services` write (line 76), the `services` pivot sync (line 88), and the `copyServiceRequirements()` call (line 94). The referral is created with case/agency/notes/documents only. `required_services` starts as `''` (column is NOT NULL with no default — keep writing `''` explicitly or set a default in the migration; prefer explicit `''` to avoid a migration).

**Interfaces:** Consumes `StoreReferralRequest` (no services fields). Produces a referral with an empty `referral_services` pivot and empty `required_services` text.

## Task 2: Keep the legacy text column in sync (derived cache)

**Files:**
- Modify: `app/Services/ReferralService.php` (near `addService()` 469-485)
- Modify: `app/Http/Controllers/ReferralController.php:450-488` (`addService()`/`removeService()`)

- [ ] **Step 1: Add `syncRequiredServicesText(Referral $referral): void`**

Private helper that recomputes `required_services` from the pivot:

```php
private function syncRequiredServicesText(Referral $referral): void
{
    $names = $referral->services()->orderBy('name')->pluck('name');
    $referral->forceFill(['required_services' => $names->implode(', ')])->save();
}
```

- [ ] **Step 2: Call it from every pivot mutation**

`ReferralService::addService()` (after `sync` + `copyServiceRequirements`) and the `removeService()` path (controller 472-488 → wherever it detaches the pivot). This keeps notifications, emails, reports, dashboard, exports, PDFs, and Admin Agency Show correct after the focal assigns services.

**Interfaces:** Consumes pivot state. Produces a consistent `required_services` text for all legacy readers.

## Task 3: Remove service selection from the creation flow

**Files:**
- Modify: `resources/js/Pages/Referral/Create.jsx`

- [ ] **Step 1: Delete service-selection state and helpers**

Remove: `services: []` from form data (line 46), `availableServices` (115-119), `selectedServiceDetails` (121-127), `selectedServiceRequirements` (129-137), the auto-select `useEffect` (139-151), `toggleServiceSelection` (193-199), `parseRequiredDocs` (201-204), and the `data.services.length > 0` clause in `isStepThreeValid` (177-179).

- [ ] **Step 2: Remove the Step 3 "Services" + "Service Requirements" UI blocks (lines 684-741)**

Keep the "Remarks" (743-756) and "Supporting Documents" (758+) blocks as the new Step 3 content. Rename the step label in the `STEPS` array (currently ~`['Case', 'Agency', 'Services']`) to `['Case', 'Agency', 'Details']` (or similar). Update `goToNextStep`/`goToPreviousStep`/`canProceed`/`submitReferral` — step 3 no longer requires a service selection, so `isStepThreeValid` reduces to `Boolean(data.case_id && data.agcy_id)`.

**Interfaces:** Consumes `agencies` prop (still needed for agency selection; `services` sub-array no longer read). Produces a 3-step create flow: Case → Agency → Details (remarks + documents).

## Task 4: Show page empty state + notification copy

**Files:**
- Modify: `resources/js/Pages/Referral/Show.jsx:1002-1027`
- Modify: `app/Notifications/ReferralCreated.php:56-65`, `PeerReferralCreated.php:47-56`

- [ ] **Step 1: Empty-state copy**

In the "Required Services" section, replace `None selected` (line 1017) with role-aware copy: for AGENCY, "No services assigned yet — add the services this referral will apply for." For other roles, "No services assigned yet. The agency focal will assign services for this referral."

- [ ] **Step 2: Notification copy**

In `ReferralCreated`/`PeerReferralCreated`, when the services string is empty, render "Services pending agency assignment" instead of a blank value.

**Interfaces:** Consumes `referral.services` / `referral.required_services`. Produces clear guidance to both the focal (act now) and the case manager (awaiting focal).

## Task 5: Tests

**Files:**
- Modify: nearest existing referral service/notification feature test
- Create: `tests/Feature/ReferralServiceAssignmentTest.php` (if no better home)

- [ ] **Step 1: Update tests that post `services`/`required_services` at creation**

`ReferralServiceNotificationTest`, `ReferralStatusChangedMailTest`, and others that assert `required_services` is stored from the create payload — creation no longer accepts these fields. Assert instead that a created referral has an empty pivot and empty text.

- [ ] **Step 2: New coverage**

1. Case manager creates a referral with no services → succeeds, pivot empty, `required_services === ''`.
2. Agency focal calls `addService` → pivot row + `referral_service_requirements` snapshot + `required_services` text updated.
3. Agency focal calls `removeService` → pivot row gone + text updated.
4. Non-AGENCY role calling `addService`/`removeService` still rejected (existing guards).

**Verification:** `vendor/bin/pint`; `php artisan test tests/Feature/ReferralServiceAssignmentTest.php`; `php artisan test --filter ReferralServiceNotificationTest`; spot-check `npm run build` for the Create/Show page changes.

---

## Out of scope (follow-ups)

- Migrating all `required_services` reads to the pivot and dropping the column (D2 alternative).
- Hiding the "Required Services" section from non-agency roles entirely (D1 alternative).
- Backfill of existing referrals whose text column drifted from the pivot (can reuse `BackfillReferralServices`).