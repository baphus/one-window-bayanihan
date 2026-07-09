# Bayanihan One Window — Testing Strategy

> **Source:** SRS v1.2 (May 19, 2026), AGENTS.md, Wave 3 test implementation
> **Last Updated:** 2026-05-28

---

## 1. Testing Philosophy

**Tests-After** — implement first, then write tests. Unit tests for services, feature tests for controllers and middleware. Database-heavy tests use SQLite in-memory with documented exceptions for PostgreSQL-specific features.

---

## 2. Test Stack

| Tool | Version | Purpose |
|---|---|---|
| PHPUnit | 11.x | Backend testing framework |
| Laravel test helpers | — | HTTP assertions, DB transactions, auth |
| SQLite (in-memory) | — | Test database (via `phpunit.xml` override) |
| `RefreshDatabase` | — | Reset DB between tests |
| Jest/Vitest | — | Frontend component testing (planned) |

---

## 3. Test Database Configuration

From `phpunit.xml`:

```xml
<server name="DB_CONNECTION" value="sqlite"/>
<server name="DB_DATABASE" value=":memory:"/>
<server name="CACHE_STORE" value="array"/>
<server name="QUEUE_CONNECTION" value="sync"/>
```

**Implications:**
- All tests run against SQLite, NOT PostgreSQL
- PostgreSQL-specific functions (`to_char`, `EXTRACT`, `age`) will fail
- Check constraints are defined differently — use `if (DB::getDriverName() !== 'sqlite')` guards in migrations
- Tests that exercise PostgreSQL-specific logic are skipped or tested at the unit level with mocked data

---

## 4. Test Categories

### 4.1 Unit Tests

| Target | Location | Coverage |
|---|---|---|
| Services | `tests/Unit/Services/` | Business logic in isolation |
| Models | `tests/Unit/Models/` | Scope, accessors, relationships |
| Form Requests | `tests/Unit/Requests/` | Validation rules |

**Current status:** Unit tests not yet written. All current tests are feature/integration tests.

### 4.2 Feature Tests (Implemented)

| Test File | Tests | Coverage |
|---|---|---|
| `IpWhitelistMiddlewareTest.php` | 5 | IP allow, block, CIDR, disabled bypass |
| `CaseReferralGuardTest.php` | 11 | canClose, toggle, archive, audit logging |
| `AuditEventViewTest.php` | 5 | VIEW audit on case/referral show, dedup, user attribution |
| `PdfExportTest.php` | 3 | Auth guard, route exists, date params |

**Total: 29 tests, all passing.**

### 4.3 Integration Tests

| Scenario | What It Validates |
|---|---|
| Full case lifecycle | Create → draft → publish → refer → track milestones → close |
| Parallel referrals | Single case referred to 2+ agencies simultaneously |
| Auth flow | Login → OTP → session → logout |
| Lane isolation | Agency A cannot access Agency B's referrals |

### 4.4 Frontend Tests (Planned)

| Tool | Scope |
|---|---|
| Vitest | Unit tests for utility functions and hooks |
| React Testing Library | Component rendering, form interactions |
| Playwright | E2E browser tests for critical paths |

---

## 5. Testing Patterns

### 5.1 Feature Test Pattern

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Case as CaseModel;

class ExampleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_manager_can_create_case(): void
    {
        $user = User::factory()->create(['role' => 'CASE_MANAGER']);

        $response = $this->actingAs($user)
            ->post(route('cases.store'), [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'client_type' => 'OFW',
                'summary' => 'Test case',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cases', ['summary' => 'Test case']);
    }
}
```

### 5.2 Auth Test Pattern

```php
public function test_unauthenticated_user_cannot_access_cases(): void
{
    $response = $this->get(route('cases.index'));
    $response->assertRedirect(route('login'));
}

public function test_agency_user_cannot_create_case(): void
{
    $user = User::factory()->create(['role' => 'AGENCY']);
    
    $response = $this->actingAs($user)
        ->post(route('cases.store'), [...]);

    $response->assertForbidden();
}
```

### 5.3 IP Whitelist Test Pattern

```php
public function test_request_from_allowed_ip_succeeds(): void
{
    Config::set('app.allowed_ips', ['192.168.1.1']);
    
    $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
        ->get(route('admin.agencies.index'));
    
    $response->assertOk();
}
```

**Note:** Use `withServerVariables(['REMOTE_ADDR' => $ip])` — NOT `withHeader('X-Forwarded-For')`, because `$request->ip()` reads `$_SERVER['REMOTE_ADDR']` directly.

### 5.4 Exception Code Pattern

When testing `abort(422)`, the thrown `HttpException` has `getCode()=0`, not 422:

```php
// CORRECT:
try {
    $response = $this->actingAs($user)->post(...);
    $this->fail('Expected HttpException');
} catch (HttpException $e) {
    $this->assertEquals(422, $e->getStatusCode());
}

// WRONG (fails):
$this->expectExceptionCode(422);
```

---

## 6. Known Test Limitations

| Limitation | Cause | Workaround |
|---|---|---|
| ReportsService tests fail | Uses `EXTRACT`, `to_char`, `age` PostgreSQL functions | Test auth guard + route existence only; skip full-stack PDF tests |
| UserFactory no role | Factory doesn't set `role` column | Always pass `['role' => '...']` when creating test users |
| PasswordReset tests fail | `/forgot-password` returns 404 | Known pre-existing issue |
| ExampleTest fails | Missing `RefreshDatabase`, hits system_settings | Known pre-existing issue |
| 8 pre-existing failures | Various auth/example tests | Documented in AGENTS.md |

---

## 7. Pre-Existing Test Failures (Wave 3 verified)

These test failures existed BEFORE Wave 3 changes and are unrelated:

| Test | Failure Reason |
|---|---|
| Auth tests (4) | User factory missing `role` → NOT NULL constraint |
| PasswordReset (2) | Route returns 404 |
| ExampleTest (1) | Hits `SystemSetting::getValue()` without `system_settings` table |
| Auth unit tests (1) | Missing auth scaffold assumptions |

**Do NOT fix these unless the task explicitly requires it.** They are pre-existing conditions documented in AGENTS.md.

---

## 8. Coverage Targets

| Layer | Current | Target |
|---|---|---|
| Feature tests (controller + middleware) | 29 tests | 80% coverage of critical paths |
| Service unit tests | 0 | 90% coverage of business logic |
| Model tests | 0 | 70% of scopes/relationships |
| Frontend component tests | 0 | 70% of components |
| E2E (critical paths) | 0 | Login, create case, make referral, track case |

### Critical Paths (Must Have E2E)

1. Welcome page → Login → OTP → Dashboard
2. Dashboard → Create Case → Fill intake → Publish
3. Case Detail → Create Referral → Select Agency → Submit
4. Login as Agency → View Referrals → Accept → Add Milestone
5. Login as DMW → Case Detail → Verify all referrals terminal → Close case
6. Public → OFW Tracking → Enter Tracker # → Receive OTP → View progress

---

## 9. Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/IpWhitelistMiddlewareTest.php

# Run with coverage (requires Xdebug/PCOV)
php artisan test --coverage

# Run specific method
php artisan test --filter test_case_manager_can_close_case
```

---

## 10. CI/CD Integration (Planned)

| Stage | Command | Frequency |
|---|---|---|
| Lint | `./vendor/bin/pint --test` | Every PR |
| Type check | `phpstan analyse` | Every PR |
| Unit + Feature tests | `php artisan test` | Every PR |
| Frontend build | `npm run build` | Every PR |
