# Ward Remediation Plan

## Evaluation

Ward v0.4.2 was initialized with 42 rules and ran all four scanners:

- `env-scanner`
- `config-scanner`
- `dependency-scanner`
- `rules-scanner`

The post-initialization report contained 155 findings. After the targeted changes in this work, the report contains 147 findings. The remaining findings are not all vulnerabilities: most are test data, QA tooling, generated chart output, or deliberately parameterized SQL that Ward's pattern scanner cannot distinguish from safe inert deserialization or fixed internal SQL.

## Completed

- Added `api-global` throttling to the three authenticated API-style message/audit-log routes Ward identified.
- Replaced MD5 cache/content fingerprints with SHA-256. These values are not password hashes, but the change removes misleading weak-crypto findings and keeps the intent explicit.
- Removed the `exec('git log ...')` fallback from `config/sentry.php`. Deployments must provide `SENTRY_RELEASE` explicitly instead of executing a shell command during configuration loading.
- Replaced failed-job object deserialization with inert property inspection using `allowed_classes=false`; notification extraction never invokes serialized methods.
- Added Ward v0.4.2 to CI with a committed reviewed baseline and a `--fail-on high` gate for new findings.
- Made the production pre-deploy database snapshot and deep readiness check fail closed when their required configuration is missing.
- Validated the changes with focused route, report, security, and email tests.

## Finding Classification

### P0: production configuration

Ward reports `APP_DEBUG=true`, `APP_ENV=local`, and an empty `DB_PASSWORD` in `.env`. These are local-development values and should not be changed in the repository's developer environment. Before every production rollout, verify:

```text
APP_ENV=production
APP_DEBUG=false
DB_PASSWORD=<non-empty secret>
```

The deployment secret store, not a committed file, is the correct remediation location.

### P0: unsafe failed-job deserialization

Failed-job email extraction now uses `unserialize(..., ['allowed_classes' => false])` and reads only inert serialized properties. It does not instantiate application mailables, notifications, models, or attacker-controlled classes, and notification recipients conservatively fall back to `(unknown)` rather than invoking notification methods.

Regression coverage in `tests/Unit/SecurityHelperTest.php` proves magic methods are not invoked. Existing failed-mail integration coverage remains green. Keep this path property-only: do not call methods on the decoded values or broaden it to a class whitelist without testing the complete Laravel payload graph.

### P1: source-code rule findings

- Weak `rand()` and `array_rand()` findings are concentrated in `database/seeders/TestingSeeder.php` and `.omo/seed_test_data.php`. These generate non-security test data and should be ignored or covered by a baseline, not replaced with cryptographic randomness.
- Static credentials in feature tests and `.omo` QA scripts are fixtures/tooling, not production secrets. Keep them out of deployment artifacts and baseline them after confirming the paths cannot ship.
- `{!! !!}` findings in `resources/views/pdf/report.blade.php` render server-generated chart image data, not user HTML. Retain the chart output tests and baseline this intentional boundary.
- SQL findings are primarily bound values, fixed PostgreSQL expressions, migrations, or allowlisted sort/column fragments. Review each dynamic identifier, preserve the existing allowlists, and add focused tests where a request value selects a SQL fragment. Do not mechanically replace SQL expressions with bindings.
- The MFA password-confirmation finding points at a test method; the controller already validates the password before disabling MFA.

### P2: scanner tuning and CI hygiene

Ward still reports the two intentional `unserialize()` call sites because v0.4.2 matches the function name without understanding the `allowed_classes=false` safety guarantee. Keep those entries documented as scanner false positives and verify them with the regression test before baselining.

After the P0/P1 review is complete:

1. Run Ward against the production-like environment configuration separately from local `.env`.
1. Generate a reviewed baseline for accepted test/tooling/intentional findings:

```powershell
ward scan . --output json --update-baseline .ward-baseline.json
```

1. Commit the baseline only after reviewing every entry, then gate new High/Critical findings:

```powershell
ward scan . --output json,sarif --baseline .ward-baseline.json --fail-on high
```

1. Revisit baseline entries whenever the affected code or scanner rule changes.

## Validation Commands

```powershell
php artisan test tests/Unit/SecurityHelperTest.php tests/Feature/EmailLoggingTest.php
php artisan test tests/Feature/ReportsAgencyScopingTest.php tests/Feature/ReferralMessageTest.php tests/Feature/Security/RateLimitingApiTest.php
php artisan config:cache
php artisan config:clear
ward scan . --output json --no-color
```

Do not use a baseline to hide the environment findings in a production deployment. Fix those values in the deployment environment and keep local development settings local.
