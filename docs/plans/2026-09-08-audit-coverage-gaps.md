# Audit Logging Coverage Gap Closure — Implementation Plan

**Goal:** Close the audit-trail coverage gaps identified in the audit coverage scan so every meaningful business action — model CRUD and service-level/security operations — produces an audit row, without breaking the existing tamper-evident hash chain.

**Architecture:** Extend the existing dual-path audit system. (1) Add the missing business-entity models to `config/audit.php` `observed_models` so the existing `AuditObserver` automatically records their create/update/delete/restore events. (2) Add explicit manual audit writes (via `SecurityAuditLogger` or `AuditLog::create`) for service-level and security operations that have no model to observe. (3) Add `$auditExclude` / `getAuditModuleName()` wiring to the newly-observed models so sensitive columns are dropped and modules are labelled correctly.

**Tech Stack:** Laravel 13, Inertia (React 18), PostgreSQL 17, PHP 8.4.

## Global Constraints

- Follow existing coding patterns (AGENTS.md).
- All PKs are UUIDs (`UsesUuid` trait) — except `SystemSetting` which uses a string `key` PK.
- Audit logging via `AuditObserver` (model events) or manual `AuditLog::create` / `SecurityAuditLogger::log` in the Service layer.
- `AuditAction` enum lives at `app/Enums/AuditAction.php` (NOT `app/Services/`). Use `AuditAction::X->value` at write sites; never cast the enum on `AuditLog` (would break the hash chain).
- `AuditModule` enum lives at `app/Enums/AuditModule.php`; use `AuditModule::tryFromLegacy($table)->value` for module strings where a case exists.
- `AuditModelCoverageTest` asserts every `observed_models` entry actually emits an audit row. Adding models is safe as long as they emit rows; do not remove entries.
- Run `vendor/bin/pint` (style) and `php artisan test` (tests) after changes.
- Do not use `withoutEvents()` for any audited write path (it suppresses the observer).

## File Map

| File | Action |
|---|---|
| `config/audit.php` | Modify — add 8 models to `observed_models` |
| `app/Models/ReferralComment.php` | Modify — add `$auditExclude`, `getAuditModuleName()` |
| `app/Models/ReferralServiceRequirement.php` | Already wired (has both) — no change |
| `app/Models/Agency.php` | Modify — add `$auditExclude`, `getAuditModuleName()` |
| `app/Models/CaseDocument.php` | Modify — add `$auditExclude`, `getAuditModuleName()` |
| `app/Models/SurveyForm.php` | Modify — add `$auditExclude`, `getAuditModuleName()` |
| `app/Models/SurveyQuestion.php` | Modify — add `$auditExclude`, `getAuditModuleName()` |
| `app/Models/SurveyInvitation.php` | Modify — add `$auditExclude`, `getAuditModuleName()` |
| `app/Models/SurveyResponse.php` | Modify — add `$auditExclude`, `getAuditModuleName()` |
| `app/Services/SecuritySettingsService.php` | Modify — add manual security audit in `update()` |
| `app/Services/CaseService.php` | Modify — add DELETE audit in `deleteDraft()` |
| `app/Services/SessionService.php` | Modify — add audit in `terminate()` |
| `app/Services/MaintenanceService.php` | Modify — add audit in `enable()`/`disable()` |
| `tests/Feature/AuditModelCoverageTest.php` | Verify still passes |
| `tests/Feature/AuditCoverageGapsTest.php` | Create — regression tests for the new coverage |

---

## Task 1: Add missing business-entity models to `observed_models`

**Files:**
- Modify: `config/audit.php:42` (`observed_models` array)

**Interfaces:**
- Consumes: nothing
- Produces: observer registration for the 8 new models (via `AppServiceProvider` loop)

- [ ] **Step 1: Add the 8 models to the `observed_models` array**

Add these `use` imports at the top of `config/audit.php`:

```php
use App\Models\Agency;
use App\Models\CaseDocument;
use App\Models\ReferralComment;
use App\Models\ReferralServiceRequirement;
use App\Models\SurveyForm;
use App\Models\SurveyInvitation;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
```

Append to the `observed_models` array (order is not significant):

```php
        ReferralComment::class,
        ReferralServiceRequirement::class,
        Agency::class,
        CaseDocument::class,
        SurveyForm::class,
        SurveyQuestion::class,
        SurveyInvitation::class,
        SurveyResponse::class,
```

- [ ] **Step 2: Verify the observer registers without error**

Run: `php artisan tinker --execute="dump(config('audit.observed_models'));"`
Expected: 26 entries (18 existing + 8 new). No exception.

---

## Task 2: Wire `$auditExclude` + `getAuditModuleName()` on the newly-observed models

**Files:**
- Modify: `app/Models/ReferralComment.php`
- Modify: `app/Models/Agency.php`
- Modify: `app/Models/CaseDocument.php`
- Modify: `app/Models/SurveyForm.php`
- Modify: `app/Models/SurveyQuestion.php`
- Modify: `app/Models/SurveyInvitation.php`
- Modify: `app/Models/SurveyResponse.php`

**Interfaces:**
- Consumes: Task 1 (models now observed)
- Produces: correct module labels + sensitive-column exclusion for the observer

**Pattern (repeat per model):**

Add a public static `$auditExclude` array and a `getAuditModuleName()` method. The audit module string should match an existing `AuditModule` case where one exists; otherwise use a snake_case table-derived string (the observer falls back to `getTable()` if the method is absent, but explicit is better).

- [ ] **Step 1: `ReferralComment`**

```php
public static array $auditExclude = [
    'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
    'referral_id', 'author_id',
];

public function getAuditModuleName(): string
{
    return 'referral_comment';
}
```

- [ ] **Step 2: `Agency`**

```php
public static array $auditExclude = [
    'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
    'password', 'remember_token',
];

public function getAuditModuleName(): string
{
    return 'agency';
}
```

> Note: `AuditModule` already has an `AGENCY = 'agency'` case, so `AuditCategory::for()` will classify these as ADMIN (via `AuditModule::category()`).

- [ ] **Step 3: `CaseDocument`**

```php
public static array $auditExclude = [
    'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
    'case_id', 'uploaded_by', 'file_path', 'storage_disk',
];

public function getAuditModuleName(): string
{
    return 'case_document';
}
```

> Exclude `file_path`/`storage_disk` — these are internal storage pointers, not meaningful audit content, and may contain sensitive bucket paths.

- [ ] **Step 4: `SurveyForm`**

```php
public static array $auditExclude = [
    'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
    'agency_id',
];

public function getAuditModuleName(): string
{
    return 'survey_form';
}
```

- [ ] **Step 5: `SurveyQuestion`**

```php
public static array $auditExclude = [
    'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
    'survey_form_id',
];

public function getAuditModuleName(): string
{
    return 'survey_question';
}
```

- [ ] **Step 6: `SurveyInvitation`**

```php
public static array $auditExclude = [
    'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
    'survey_form_id', 'case_id', 'agency_id', 'referral_id',
    'token', 'token_hash',
];

public function getAuditModuleName(): string
{
    return 'survey_invitation';
}
```

> `token`/`token_hash` are excluded here AND covered by the `config/audit.php` redact patterns (`'token'`). Defense in depth.

- [ ] **Step 7: `SurveyResponse`**

```php
public static array $auditExclude = [
    'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
    'survey_invitation_id', 'survey_form_id', 'case_id', 'agency_id',
];

public function getAuditModuleName(): string
{
    return 'survey_response';
}
```

- [ ] **Step 8: Run the coverage test**

Run: `php artisan test tests/Feature/AuditModelCoverageTest.php`
Expected: PASS (all 26 observed models emit an audit row).

---

## Task 3: Audit `SecuritySettingsService::update` (HIGH)

**Files:**
- Modify: `app/Services/SecuritySettingsService.php:46`

**Interfaces:**
- Consumes: `SecurityAuditLogger::log()`, `AuditAction`
- Produces: SECURITY-category audit row for security-policy changes

- [ ] **Step 1: Add a manual security audit after the settings loop**

Modify `update()` so that after applying all settings, it records a single SECURITY audit entry summarizing which settings changed:

```php
use App\Enums\AuditAction;
use App\Services\SecurityAuditLogger;

public function update(array $data): void
{
    $settings = [
        'password_min_length' => 'int',
        'password_require_special' => 'bool',
        'password_require_numbers' => 'bool',
        'password_expiry_days' => 'int',
        'session_lifetime_minutes' => 'int',
        'max_login_attempts' => 'int',
        'lockout_duration_minutes' => 'int',
        'ip_whitelist_enabled' => 'bool',
        'ip_whitelist_ips' => 'string',
        'two_factor_required' => 'bool',
    ];

    $changedKeys = [];

    foreach ($settings as $key => $type) {
        if (array_key_exists($key, $data)) {
            SystemSetting::setValue($key, $data[$key], 'security', "Security setting: $key");
            $changedKeys[] = $key;
        }
    }

    if ($changedKeys !== []) {
        SecurityAuditLogger::log(
            'security_settings',
            'Security settings updated: '.implode(', ', $changedKeys),
            null,
            AuditAction::UPDATE->value,
        );
    }
}
```

> **Security note:** Never include the *values* of `ip_whitelist_ips` or any secret in the description — only the changed key *names*. `SecurityAuditLogger` writes a pre-built safe description with no old/new values.

- [ ] **Step 2: Run the focused test**

Run: `php artisan test --filter=SecuritySettings`
Expected: PASS (existing tests still green).

---

## Task 4: Audit `CaseService::deleteDraft` (MEDIUM)

**Files:**
- Modify: `app/Services/CaseService.php:193` (`deleteDraft`)

**Interfaces:**
- Consumes: `AuditLog::create`, `AuditAction`, `AuditModule`
- Produces: DELETE audit row for draft hard-deletion (which currently runs inside `withoutEvents`)

- [ ] **Step 1: Add a manual DELETE audit inside `deleteDraft`**

`deleteDraft` currently hard-deletes the draft + client PII inside `withoutEvents`, so the observer never fires. Add an explicit audit write before/after the deletion. Capture the case number and client name for the description:

```php
use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// Inside deleteDraft(), before the withoutEvents block, capture identifiers:
$caseNumber = $caseFile->case_number ?? null;
$clientName = trim(($caseFile->client->first_name ?? '').' '.($caseFile->client->last_name ?? ''));

// ... existing withoutEvents deletion ...

// After deletion, write the audit row (outside withoutEvents so it persists):
$request = request();
AuditLog::create([
    'action' => AuditAction::DELETE->value,
    'module' => AuditModule::CASE->value ?? 'case',
    'entity_id' => $caseFile->id,
    'description' => sprintf(
        'Draft case %s for %s was permanently deleted',
        $caseNumber ?? 'N/A',
        $clientName !== '' ? $clientName : 'unknown client',
    ),
    'user_id' => Auth::id(),
    'timestamp' => now(),
    'ip_address' => $request?->ip() ?? 'cli',
    'user_agent' => $request?->userAgent() ?? 'cli',
    'request_id' => $request?->attributes->get('correlation_id')
        ?? $request?->header('X-Request-ID')
        ?? (string) Str::uuid(),
]);
```

> **Important:** The audit write must be OUTSIDE the `withoutEvents` closure, and must not itself be wrapped in `withoutEvents`. If `deleteDraft` runs inside a DB transaction, the audit row commits atomically with the deletion — which is the desired behavior.

- [ ] **Step 2: Run the focused test**

Run: `php artisan test --filter=DeleteDraft`
Expected: PASS.

---

## Task 5: Audit `SessionService::terminate` (LOW)

**Files:**
- Modify: `app/Services/SessionService.php:40` (`terminate`)

**Interfaces:**
- Consumes: `SecurityAuditLogger::log`, `AuditAction`
- Produces: SECURITY audit row for session termination

- [ ] **Step 1: Add a security audit before the raw session delete**

```php
use App\Enums\AuditAction;
use App\Services\SecurityAuditLogger;

public function terminate($session): void
{
    SecurityAuditLogger::log(
        'session',
        'User session was terminated',
        (string) $session->id,
        AuditAction::DELETE->value,
    );

    $session->delete(); // existing raw delete
}
```

> `SecurityAuditLogger` stamps the SECURITY category because the `session` module is a security surface (via `AuditModule`). The entity_id is the session id.

- [ ] **Step 2: Run the focused test**

Run: `php artisan test --filter=Session`
Expected: PASS.

---

## Task 6: Audit `MaintenanceService::enable/disable` (LOW)

**Files:**
- Modify: `app/Services/MaintenanceService.php:30` (`enable`), `:45` (`disable`)

**Interfaces:**
- Consumes: `SecurityAuditLogger::log`, `AuditAction`
- Produces: SYSTEM/SECURITY audit row for maintenance-mode toggles

- [ ] **Step 1: Add audits to both methods**

```php
use App\Enums\AuditAction;
use App\Services\SecurityAuditLogger;

public function enable(): void
{
    SecurityAuditLogger::log(
        'maintenance',
        'Maintenance mode was enabled',
        null,
        AuditAction::UPDATE->value,
    );

    // ... existing artisan down logic ...
}

public function disable(): void
{
    SecurityAuditLogger::log(
        'maintenance',
        'Maintenance mode was disabled',
        null,
        AuditAction::UPDATE->value,
    );

    // ... existing artisan up logic ...
}
```

> These are admin actions; `SecurityAuditLogger` records the acting user. If triggered from the console (no auth), `AuditCategory::for()` classifies them as SYSTEM.

- [ ] **Step 2: Run the focused test**

Run: `php artisan test --filter=Maintenance`
Expected: PASS.

---

## Task 7: Regression tests for new coverage

**Files:**
- Create: `tests/Feature/AuditCoverageGapsTest.php`

**Interfaces:**
- Consumes: all Tasks 1–6
- Produces: automated proof that the gaps are closed

- [ ] **Step 1: Write the regression test**

```php
<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseDocument;
use App\Models\ReferralComment;
use App\Models\ReferralServiceRequirement;
use App\Models\SurveyForm;
use App\Models\SystemSetting;
use App\Services\SecuritySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_settings_update_writes_audit_row(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        (new SecuritySettingsService())->update(['password_min_length' => 12]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'security_settings',
            'action' => AuditAction::UPDATE->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_referral_comment_create_writes_audit_row(): void
    {
        $this->actingAs($this->createAgencyUser());

        $comment = ReferralComment::create([
            'referral_id' => $this->createReferral()->id,
            'author_id' => $this->createAgencyUser()->id,
            'content' => 'Test comment',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'referral_comment',
            'action' => AuditAction::CREATE->value,
            'entity_id' => $comment->id,
        ]);
    }

    public function test_referral_service_requirement_create_writes_audit_row(): void
    {
        $this->actingAs($this->createAgencyUser());

        $rsr = ReferralServiceRequirement::create([
            'referral_id' => $this->createReferral()->id,
            'service_id' => $this->createService()->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'referral_service_requirement',
            'action' => AuditAction::CREATE->value,
            'entity_id' => $rsr->id,
        ]);
    }

    public function test_agency_create_writes_audit_row(): void
    {
        $this->actingAs($this->createAdminUser());

        $agency = Agency::create([
            'name' => 'Test Agency',
            'email' => 'agency@example.com',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'agency',
            'action' => AuditAction::CREATE->value,
            'entity_id' => $agency->id,
        ]);
    }

    public function test_case_document_create_writes_audit_row(): void
    {
        $this->actingAs($this->createCaseManagerUser());

        $doc = CaseDocument::create([
            'case_id' => $this->createCaseFile()->id,
            'uploaded_by' => $this->createCaseManagerUser()->id,
            'file_name' => 'test.pdf',
            'file_path' => 'cases/test.pdf',
            'storage_disk' => 'object-storage',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'case_document',
            'action' => AuditAction::CREATE->value,
            'entity_id' => $doc->id,
        ]);
    }

    public function test_survey_form_create_writes_audit_row(): void
    {
        $this->actingAs($this->createAgencyUser());

        $form = SurveyForm::create([
            'agency_id' => $this->createAgency()->id,
            'title' => 'Satisfaction Survey',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'survey_form',
            'action' => AuditAction::CREATE->value,
            'entity_id' => $form->id,
        ]);
    }

    // Helper factories (implement against the real model fillables):
    private function createAdminUser() { /* ... */ }
    private function createAgencyUser() { /* ... */ }
    private function createCaseManagerUser() { /* ... */ }
    private function createAgency() { /* ... */ }
    private function createReferral() { /* ... */ }
    private function createService() { /* ... */ }
    private function createCaseFile() { /* ... */ }
}
```

> **Note:** The helper factories must be implemented against the real model fillables/required columns. Check each model's `$fillable` and required DB columns before writing them. The `createReferral`/`createCaseFile`/`createService` helpers may already exist in an existing test base or `TestCase` — reuse them if present.

- [ ] **Step 2: Run the new test**

Run: `php artisan test tests/Feature/AuditCoverageGapsTest.php`
Expected: PASS (all new coverage assertions green).

- [ ] **Step 3: Run the full audit test suite**

Run: `php artisan test tests/Feature/AuditModelCoverageTest.php tests/Feature/AuditCoverageGapsTest.php`
Expected: PASS.

---

## Task 8: Full verification

**Files:**
- All modified files

**Interfaces:**
- Consumes: all tasks

- [ ] **Step 1: Run Pint style check**

Run: `vendor/bin/pint --test`
Expected: PASS. If failures, run `vendor/bin/pint` to fix.

- [ ] **Step 2: Run the full test suite**

Run: `composer run test`
Expected: PASS (or only pre-existing unrelated failures).

- [ ] **Step 3: Confirm hash-chain integrity is unaffected**

Run: `php artisan audit:verify`
Expected: PASS (no new chain breaks introduced — the new writes go through the same `AuditLog::create` path with advisory-lock serialization).

---

## Out of Scope (documented, not fixed)

These were identified as gaps but are intentionally **not** addressed by this plan:

- **`CaseEvent`** — client-facing display timeline, append-only, distinct from the audit log. Adding it to `observed_models` would double-log every case lifecycle event as both a `CaseEvent` and an `AuditLog`. Keep separate.
- **`CaseNotification`** — high-volume, low-value notification rows; auditing them adds noise without meaningful evidence value.
- **`AuditArchiveService` / artisan backfill/prune commands** — meta-operations with their own integrity mechanisms (checksums, chain contiguity).

If these become requirements later, they should be separate changes with their own noise/retention analysis.

---

## Self-Review Checklist

- [ ] **Spec coverage** — every priority gap (HIGH/MEDIUM) has a task: SecuritySettings (T3), ReferralComment (T1+T2), ReferralServiceRequirement (T1), Agency (T1+T2), CaseDocument (T1+T2), deleteDraft (T4), Session (T5), Maintenance (T6). LOW survey gaps covered by T1+T2.
- [ ] **Placeholder scan** — no TBD/TODO; helper factories in T7 are marked for real implementation against model fillables.
- [ ] **Type consistency** — `AuditAction::X->value` used everywhere (never enum-cast on AuditLog); `SecurityAuditLogger::log(module, description, entityId, action)` signature matches existing.
- [ ] **Test safety** — `AuditModelCoverageTest` still passes (only additions, no removals); new regression test added.
