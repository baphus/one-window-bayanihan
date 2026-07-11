# Testing Strategy

> **Version:** 2.0.0 | **Updated:** 2026-07-11 | **Source:** `phpunit.xml`, `vitest.config.ts`, `playwright.config.ts`, `tests/`

## Overview

| Layer | Tool | Test DB | Config |
|-------|------|---------|--------|
| PHP Feature Tests | PHPUnit 12 | PostgreSQL `bayanihan_test` | `phpunit.xml` |
| PHP Unit Tests | PHPUnit 12 | PostgreSQL `bayanihan_test` | `phpunit.xml` |
| Frontend Unit Tests | Vitest 4 + Testing Library | JSDOM (no DB) | `vitest.config.ts` |
| E2E Tests | Playwright | Live server (port 8000) | `playwright.config.ts` |

## Commands

```bash
# PHP tests (all)
composer run test              # Clears config cache, then runs php artisan test

# PHP tests (focused)
php artisan test tests/Feature/CaseServiceTest.php
php artisan test --filter test_case_manager_can_create_case

# Frontend unit tests
npm run test:run               # Vitest one-shot
npm test                       # Vitest watch mode

# E2E tests
npm run test:e2e               # Playwright (auto-starts server on :8000)

# Code style
./vendor/bin/pint --test       # Check PHP style (Laravel Pint)
./vendor/bin/pint              # Fix PHP style
```

## Test Database Configuration

**CRITICAL:** Tests use PostgreSQL, NOT SQLite. The `phpunit.xml` explicitly configures:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="bayanihan_test"/>
```

### Prerequisites

1. A PostgreSQL database named `bayanihan_test` must exist locally
2. The database user must have full DDL/DML privileges
3. Run migrations against the test database: `php artisan migrate --database=pgsql --env=testing`

### Why PostgreSQL (not SQLite)

- Application uses PostgreSQL-specific functions: `to_char()`, `EXTRACT()`, `age()`
- JSONB columns with `jsonb_path_ops` GIN indexes
- Partial unique indexes (`WHERE` clause)
- CHECK constraints
- `pg_trgm` extension for text search
- Row-Level Security policies
- Append-only triggers on `audit_logs`

### Test Environment Overrides (phpunit.xml)

| Setting | Test Value | Notes |
|---------|-----------|-------|
| `APP_ENV` | `testing` | |
| `CACHE_STORE` | `array` | In-memory, no DB cache table |
| `QUEUE_CONNECTION` | `sync` | Jobs execute immediately |
| `SESSION_DRIVER` | `array` | In-memory sessions |
| `MAIL_MAILER` | `array` | No real emails sent |
| `SUPABASE_S3_DRIVER` | `local` | Fake local storage |
| `SUPABASE_S3_ACCESS_KEY` | `fake-access-key` | |
| `BCRYPT_ROUNDS` | `4` | Faster password hashing |
| `memory_limit` | `512M` | Large test data sets |

## Test Structure

```
tests/
├── TestCase.php                          # Base test class
├── Feature/                              # Integration tests (HTTP + DB)
│   ├── Auth/                             # Authentication flow tests
│   │   ├── AuthenticationTest.php
│   │   ├── OtpSessionBindingTest.php
│   │   ├── OtpResendAuthTest.php
│   │   ├── EmailChangeTest.php
│   │   ├── ForgotEmailTest.php
│   │   ├── RegistrationTest.php
│   │   ├── PasswordUpdateTest.php
│   │   ├── PasswordResetTest.php
│   │   ├── PasswordConfirmationTest.php
│   │   └── EmailVerificationTest.php
│   ├── Security/                         # Security control tests
│   │   ├── SecurityHeadersTest.php
│   │   ├── CspHeadersTest.php
│   │   ├── TurnstileValidationTest.php
│   │   ├── RateLimitingApiTest.php
│   │   ├── MfaDisablePasswordTest.php
│   │   ├── MalwareScannerTest.php
│   │   ├── ReferralAccessTest.php
│   │   ├── OverdueReferralsAccessTest.php
│   │   ├── FileExtensionValidationTest.php
│   │   ├── CaseDocumentUploadValidationTest.php
│   │   ├── StorageServiceMimeValidationTest.php
│   │   ├── AvatarAccessorTest.php
│   │   ├── UserModelHiddenTest.php
│   │   ├── UserMassAssignmentTest.php
│   │   ├── ApiVerifiedMiddlewareTest.php
│   │   ├── ErrorHandlerTest.php
│   │   └── TrustProxiesTest.php
│   ├── Admin/                            # Admin CRUD tests
│   │   └── AdminUserVerifyTest.php
│   ├── TrackController/                  # Public tracking tests
│   │   ├── IndexTest.php
│   │   ├── SendOtpTest.php
│   │   ├── VerifyOtpTest.php
│   │   ├── ShowTest.php
│   │   └── MilestonesTest.php
│   ├── TrackingService/                  # Tracking service integration
│   │   ├── FullLifecycleIntegrationTest.php
│   │   ├── ReferralLifecycleIntegrationTest.php
│   │   ├── BuildMilestoneTimelineTest.php
│   │   ├── BuildAgencyStepsTest.php
│   │   ├── CaseStatusAuditTrailTest.php
│   │   ├── FindCaseByTrackerTest.php
│   │   ├── EdgeCasesTest.php
│   │   └── AuditLogFormatterTest.php
│   ├── Export/                           # Export tests
│   │   ├── PageExportTest.php
│   │   └── DataExportTest.php
│   ├── Case*Test.php                     # Case management tests (9 files)
│   ├── Referral*Test.php                 # Referral tests (6 files)
│   ├── Feedback*Test.php                 # Feedback/SERVQUAL tests (4 files)
│   ├── Chatbot*Test.php                  # AI chatbot tests (4 files)
│   ├── AuditLog*Test.php                 # Audit logging tests (4 files)
│   ├── Reports*Test.php                  # Reports/analytics tests (4 files)
│   ├── Mfa*Test.php                      # MFA tests (2 files)
│   ├── Client*Test.php                   # Client management tests (4 files)
│   └── (other feature tests)            # ~20 additional files
└── Unit/                                 # Isolated unit tests
    ├── Services/
    │   ├── StorageServiceTest.php
    │   ├── NotificationServiceTest.php
    │   └── IncidentIdServiceTest.php
    ├── Exceptions/
    │   ├── SafeExceptionTest.php
    │   └── ErrorCodesTest.php
    ├── Notifications/
    │   ├── MilestoneAddedTest.php
    │   ├── CaseUpdatedTest.php
    │   └── CaseStatusUpdatedTest.php
    ├── Models/
    │   └── CaseNotificationTest.php
    ├── SecurityHelperTest.php
    ├── ReportsDataExportServiceTest.php
    └── OtpServiceTest.php
```

## Test Count (Approximate)

| Category | Files | Estimated Tests |
|----------|-------|----------------|
| Feature/Auth | 10 | ~30 |
| Feature/Security | 17 | ~50 |
| Feature/Cases | 9 | ~60 |
| Feature/Referrals | 6 | ~35 |
| Feature/Feedback | 4 | ~40 |
| Feature/Chatbot | 4 | ~25 |
| Feature/Tracking | 13 | ~70 |
| Feature/Reports | 4 | ~25 |
| Feature/Audit | 4 | ~30 |
| Feature/Other | ~20 | ~60 |
| Unit | ~12 | ~40 |
| **Total** | **~103** | **~465** |

## Test Patterns

### Feature Test Pattern

```php
class CaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_manager_can_create_case(): void
    {
        $user = User::factory()->caseManager()->create();
        
        $this->actingAs($user)
            ->post(route('cases.store'), [...])
            ->assertRedirect(route('cases.show', $case));
        
        $this->assertDatabaseHas('cases', [...]);
    }
}
```

### Key Testing Patterns

1. **RefreshDatabase trait** — Wraps each test in a transaction (rolled back)
2. **Model factories** — `User::factory()->caseManager()`, `->agency()`, `->admin()`
3. **actingAs($user)** — Simulates authenticated user
4. **assertDatabaseHas/Missing** — Verifies data state
5. **Inertia assertions** — `assertInertia(fn ($page) => $page->component('X'))`
6. **Notification fakes** — `Notification::fake()` for email verification
7. **Storage fakes** — `Storage::fake('supabase')` for file upload tests
8. **Queue sync** — All jobs run synchronously in tests

## Frontend Test Configuration

### Vitest (`vitest.config.ts`)

```typescript
export default defineConfig({
  test: {
    environment: 'jsdom',
    setupFiles: ['./resources/js/test-setup.ts'],
    globals: true,
  },
  resolve: {
    alias: { '@': '/resources/js' }
  }
});
```

### Playwright (`playwright.config.ts`)

- Runs on port 8000 (auto-starts `php artisan serve --port=8000`)
- Browser: Chromium (headless)
- Timeout: 30 seconds per test

## CI/CD Integration

Tests are run via:
```bash
# In CI pipeline
composer run test              # PHP tests
npm run test:run               # Frontend unit tests
npm run test:e2e               # E2E tests (requires running server)
```

## Coverage Expectations

| Area | Target | Notes |
|------|--------|-------|
| Auth/Security | High | Critical path — all flows tested |
| Case CRUD | High | Core business logic |
| Referral lifecycle | High | Core workflow |
| Reports | Medium | Complex aggregations, harder to test |
| Admin CRUD | Medium | Standard CRUD patterns |
| Chatbot | Medium | Mocked AI responses |
| UI Components | Low | Manual testing preferred for now |
