# Migrate the remaining role string literals to UserRole

Status: planned, not started. Origin: second whole-repo audit pass (2026-10-09), shipped as PR #189.

## What already exists

PR #189 landed the foundation:

- `app/Enums/UserRole.php` — string enum `CASE_MANAGER | AGENCY | ADMIN | OFW` with
  `values()`, `staffValues()`, `isStaff()`, `label()`.
  Deliberately **not** an Eloquent cast — `users.role` is compared as a plain string in
  ~166 places, and casting would change `where('role', ...)` behaviour everywhere.
- `app/Http/Middleware/CheckRole.php` — strict `in_array(..., true)` plus a static
  `allowed()` helper.
- `config/mfa.php` — `enrollment_enforced_roles` is now `implode(',', UserRole::staffValues())`.
- `app/Models/User.php` — `roleEnum(): ?UserRole` and `hasRole(UserRole $role): bool`.
- `tests/Feature/UserRoleConsistencyTest.php` — asserts every `role:` literal on every
  registered route is a real enum case, that MFA enforced roles are real cases, and that
  the factory only produces known roles.

## What is left

Approximately 160 remaining raw role strings across:

| Location | Shape |
|---|---|
| `app/**` | `$user->role === 'ADMIN'`, `->where('role', 'AGENCY')`, `match ($user->role) { ... }` |
| `routes/web.php` | `'role:CASE_MANAGER,ADMIN'` middleware arguments |
| `config/*.php` | other role lists beyond `mfa.php` |
| `resources/js/**` | `role === 'ADMIN'` / `'CASE_MANAGER'` branch checks in pages and components |

## Why not one mechanical sed

- `'CASE_MANAGER'` in a `where('role', ...)` is a **column value**; `UserRole::CASE_MANAGER->value`
  is the enum. A blind rewrite can produce `UserRole::CASE_MANAGER` where the DB needs the string.
  Both compile; only one runs.
- `match ($user->role)` arms are plain strings today. Changing them to enum cases changes
  the match type to the enum, which requires the subject to be an enum too — a different
  change than swapping a literal.
- JS has no enum, only constants. `resources/js` needs a `UserRole` const object or the
  backend's role list mirrored, which is a separate decision.

## Proposed order

### Step 1 — Type-safe sites first (lowest risk)

`$user->role === 'X'` comparisons in `app/**`. These are exact-value checks, so
`UserRole::ADMIN->value` or `$user->hasRole(UserRole::ADMIN)` is a direct, provable swap.
Some are already better as `$user->roleEnum()?->isStaff()` — prefer that where the intent
is "any staff", not "this specific role".

### Step 2 — Query sites

`->where('role', 'AGENCY')` becomes `->where('role', UserRole::AGENCY->value)`. Verify
each one still matches a real column value; the test suite will catch a mistake here
because role scoping is thoroughly covered.

### Step 3 — Route middleware arguments

`'role:CASE_MANAGER,ADMIN'` → `'role:'.implode(',', UserRole::staffValues())`, or leave as-is
since `UserRoleConsistencyTest` already guards them. Recommend: **leave as-is** and rely on
the tripwire test. Converting them buys nothing the test does not already give.

### Step 4 — `config/` role lists

Find every other list beyond `mfa.php` and route it through `UserRole::values()` or
`UserRole::staffValues()`.

### Step 5 — `resources/js/`

Separate decision required: either a `resources/js/lib/userRole.js` const mirroring the PHP
enum, or expose the role list as an Inertia shared prop and import it. **Recommend the
shared prop** — it keeps one source of truth instead of two that must be updated together.
Needs its own plan; do not guess.

## Verification per step

```
php artisan test --filter=UserRole
php artisan test tests/Feature/UserRoleConsistencyTest.php
vendor/bin/pint --dirty --format agent
```

The tripwire test is the point of this whole exercise: it fails loudly if any `role:` literal
on any route stops being a real case, so every step is self-checking.

At the end, the full suite must stay at or above **1739 passed, 7613 assertions**.

## Definition of done

- [ ] Zero raw `'ADMIN'` / `'CASE_MANAGER'` / `'AGENCY'` / `'OFW'` string literals outside
      `app/Enums/UserRole.php`, database seeders, and migrations
- [ ] `UserRoleConsistencyTest` still passes (it is the tripwire)
- [ ] `npm run test:run` still 293+ passing
- [ ] Full PHP suite at or above 1739 passed / 7613 assertions
- [ ] `pint --dirty` clean

## Notes

- `UserRoleConsistencyTest` asserts "the factory only produces known roles". If the factory
  is changed to use the enum, that assertion stays but becomes circular — replace it with an
  assertion over `UserRole::cases()` directly when that happens.
- Do **not** add an Eloquent cast to `UserRole`. `users.role` is a plain `string(50)` column
  and ~166 sites compare it as a string; a cast is a behaviour change disguised as a cleanup.
