# Split the five god services

Status: planned, not started. Origin: second whole-repo audit pass (2026-10-09), shipped as PR #189.

## The problem

Five classes mix querying, filtering, role-scoping, formatting and export shaping for
several unrelated tables each. None of them is one thing.

| File | Lines |
|---|---|
| `app/Services/CaseService.php` | 1726 |
| `app/Services/ReportsService.php` | 1650 |
| `app/Services/ReferralService.php` | 1261 |
| `app/Services/DashboardService.php` | 1232 |
| `app/Services/Export/DataExportQueries.php` | 1152 |
| **Total** | **6442** |

**Risk today.** Every change to any of these files means reading and reasoning about the
other four concerns in the same class. Tests that touch one concern drag the whole class
into context. There is no seam to mock, so feature tests go to the HTTP boundary, which is
why the suite takes ~350s.

## Rules for this refactor

1. **No behaviour change.** Every step is a pure move. If a step changes a test assertion
   other than a namespace/import, the step is wrong — stop and re-cut it.
2. **One service at a time, one PR each.** Never two services in one PR; the blast radius
   makes a revert expensive.
3. **Split by responsibility, never by line count.** A 300-line class that does one thing
   stays. A 200-line class that does four things does not.
4. **Read before cutting.** Each of the 6,442 lines gets read once, first, before any edit.
   The current estimates of "what belongs where" are guesses until then.
5. **Deletion beats addition.** If a move leaves an old wrapper with no callers, delete it.

## Order and rationale

### Step 1 — `DataExportQueries.php` (1152)

Best starting point: it is already table-decomposed. `fullExportSheets()`
(`:55-84`) is a map from table name to a `fn () => $this->getX($user)` closure, so it is
nearly a seam already. The natural split is one query-builder class per sheet group
(cases+clients, referrals+agencies+services, milestones+requirements, audit-adjacent).

Deliverable: `DataExportQueries` becomes a thin orchestrator delegating to
`app/Services/Export/Queries/*`, one class per sheet group. Its public surface
(`fullExportSheets`, `getCasesExport`, and the `getX` methods used by
`DataExportService` and `GenerateSystemReport`) does not change.

### Step 2 — `ReferralService.php` (1261)

Cleanest boundary of the remaining four: `createReferral()` is a self-contained
transaction that already reads as "create the referral, fire the events, notify the
parties". Split the notification fan-out (agency users, peer agencies, OFW email —
`:96-142`) into a `ReferralNotifier`, and the status-machine (`STATUS_TRANSITIONS`
`:48-54` plus `updateStatus`) into a `ReferralStatusMachine`.

`referralStatsCacheKey()` / `invalidateReferralStats()` are already a static pair and
move as-is.

### Step 3 — `ReportsService.php` (1650)

Worst first-runner: the "get the rows" and "render the chart data" halves are already
interleaved. Only start this after step 1 has proved the query-layer extraction works,
so the same pattern can be reused rather than reinvented.

### Step 4 — `DashboardService.php` (1232) and `CaseService.php` (1726)

Largest, most entangled, least obviously decomposable. Do them last, and only if the
first three produced a pattern worth repeating. If by then no seam has emerged that is
clearly right, stop and write up why rather than forcing one.

## Verification per step

```
php artisan test --filter=<the area the service serves>
vendor/bin/pint --dirty --format agent
```

Then, at the end of each PR, the full suite must stay at or above the current count:

```
php artisan config:clear && php artisan test
```

Baseline to hold: **1739 passed, 7613 assertions**. A PR that reduces the test count has
silently dropped coverage.

## Definition of done

- [ ] All 6,442 lines read before the first edit in each service
- [ ] Each PR is a pure move: no assertion changes beyond namespace updates
- [ ] No service exceeds ~400 lines once split
- [ ] Full suite at or above 1739 passed / 7613 assertions
- [ ] `pint --dirty` clean
- [ ] The `ponytail:` comments in the touched files survive the move unchanged
