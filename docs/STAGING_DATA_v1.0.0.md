# Staging Data (StagingSeeder)

> **Version:** 1.0.0 | **Reviewed:** 2026-11-01
> **Source of truth:** `database/seeders/StagingSeeder.php`
> **Scope:** Disposable local/staging demo data, not production fixtures or deployment evidence.

## Implementation

One Faker-based `StagingSeeder` builds a six-month window ending at `Carbon::now()->subMinutes(5)`. It does not use a `database/seeders/Staging/` helper architecture or `config/staging.php`; those files are absent. No fixed Faker seed, business-day anchor, agency-hours calendar, byte-identical rerun, or measured runtime is guaranteed.

The seeder invokes reference seeders, then creates users, clients, cases, referrals, collaboration records, client requests, surveys, email logs, and an audit trail. Volumes are constants in this single file. It writes attachment/document metadata, not uploaded object contents. Demo names, email addresses, and client contact numbers are plaintext as in the models; date of birth and selected address, employment, and next-of-kin fields are encrypted.

## Safety and execution

**This command destroys data.** `truncateOwnedTables()` runs `TRUNCATE ... RESTART IDENTITY CASCADE` for `OWNED_TABLES` inside a transaction. Cascades can remove dependent runtime records. Users and reference tables are not in that list, but user records are updated by the seed process.

1. Confirm the database is disposable and backups are available if needed.
2. Confirm `APP_ENV` is `local` or `staging`. `STAGING_SEEDER_ALLOWED_ENVS` can override the environment guard; never use that override to permit production.
3. Run `php artisan db:seed --class=StagingSeeder`.
4. Inspect the reported counts and automatic `audit:verify` result.

Audit verification runs after the transaction commits. A verification failure throws but leaves the committed dataset for investigation; do not manually alter audit rows to hide failure. Reruns replace owned data with newly generated values, not identical data. Demo credentials are defined in `seedUsers()` and must never be reused outside a disposable environment.

## Operations boundary

The repository has no staging deployment caller, daily reset workflow, or reset-comment bot. An environment owner must arrange any approved manual reset. For test fixtures and commands, see [Testing Strategy](TESTING_STRATEGY_v2.1.0.md).
