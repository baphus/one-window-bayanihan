# Reports & Export — Current Behavior

> **Version:** 1.1.0 | **Updated:** 2026-09-15 | **Source:** `app/Http/Controllers/ReportsController.php`, `app/Services/ReportsService.php`, `app/Services/Reports/ReportsExportService.php`, `app/Services/Reports/PdfChartRenderer.php`, `app/Services/DashboardService.php`, `app/Services/Export/DataExportService.php`, `config/reports.php`, `resources/views/pdf/report.blade.php`
>
> **Scope:** `GET /reports`, `GET /reports/export-pdf`, `GET /reports/export-excel` (CASE_MANAGER, ADMIN, AGENCY).
> **Relationship to `docs/REPORTS_EXPORT_DESIGN_v1.0.0.md`:** that record is the measurement evidence behind the rebuild (PDF outage root cause, Excel memory/timeout fix, unfiltered-panels fix, limits, review findings) and is retained as history. This document describes **current post-rebuild behavior** — what the routes do today, the numbers behind the current limits, and how to operate them. If a limit changes, the new measurement belongs alongside the old one, not in place of it.

## 1. Routes and Role Scoping

| Route | Throttle | Notes |
|-------|----------|-------|
| `GET /reports` (`reports.index`) | `throttle:reports-view` (120/min) | Screen dashboard; expensive queries, hence the limiter |
| `GET /reports/export-pdf` (`reports.export-pdf`) | pre-flight row-cap guard | Presentation document (DomPDF via `barryvdh/laravel-dompdf`) |
| `GET /reports/export-excel` (`reports.export-excel`) | pre-flight row-cap guard | Machine-readable workbook (PhpSpreadsheet **5.9.0**, pinned in `composer.json`) |

Agency scoping: AGENCY callers are locked to their own agency (`$user->agency->id`); ADMIN and CASE_MANAGER may pass `agency_id` or view all. An export must never widen a role's visibility beyond its own dashboard — `ReportsExportService::AGENCY_HIDDEN_SHEETS` / `AGENCY_HIDDEN_SECTIONS` drop agency-invisible sheets and payload sections, asserted by test. Cross-agency workload tables in CASE_MANAGER exports are intentional (case managers already receive cross-agency scorecards); the no-widening rule binds AGENCY, where the dashboard is the boundary of what one agency may see about the programme.

## 2. Query Layer (PostgreSQL-Native)

Reports and dashboard code is PostgreSQL-native — `to_char`, `EXTRACT`, `age`, `FILTER` — and assumes PostgreSQL-compatible data (no SQLite semantics):

- **Time buckets:** `to_char(<column>, 'YYYY-MM')` for month labels in `ReportsService` and `ReportsExportService`.
- **Averages:** `AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400)` for completion days (`ReportsService` referral completion and case resolution averages).
- **Overdue threshold:** `EXTRACT(EPOCH FROM (NOW() - referrals.created_at)) / 86400 > 14` — referrals older than **14 days** and still open count as overdue. The export computes this from its own filtered base (not from the eager-loaded `caseFile.client` path, which would pull client PII into a PII-free document and ignores the date window).
- **Dashboard bands:** `DashboardService` uses `COUNT(*) FILTER (WHERE …)` age bands (0–2, 3–5, 6–10, 11+ days) for queues and scorecards.
- **Geography filtering:** applied as a **subquery, not a join** (`applyCaseWindow`), so a client with two addresses is not double-counted in `count(*)`.

## 3. What the Exports Contain

### PDF (presentation document, not a ledger)

- Cover page: title, full filter set, generation provenance, confidentiality marking.
- Typography on DejaVu Sans (dompdf's bundled Unicode face) — nothing below 8px.
- Charts are PNGs from `PdfChartRenderer` (GD; TrueType-guarded with a bitmap-font fallback so a missing FreeType build costs label quality, not the feature). dompdf drops inline SVG — never embed SVG geometry.
- Long category lists chart the top 15 with the full set in the companion table; the chart subtitle states the cap.
- Appendix: the 200 highest-risk active referrals/cases, ranked in SQL (`ORDER BY` + `LIMIT` in the database, totals from a separate `COUNT`), each stating "showing N of M" and pointing at Excel for the remainder. No silent truncation.
- Provenance page: schema version, timestamps, matched row counts, appendix limit, suppression status.

### Excel (machine-readable counterpart, 27 sheets)

- One sheet per section in PDF order. Counts are `int`, rates are `percent`, timestamps are `datetime` — real cell types, sortable and pivotable. Identifiers stay forced-text (no scientific-notation mangling); the formula-injection guard on text cells is retained.
- Native charts on the nine headline sheets, bound to their cells; skipped above 60 categories; a chart failure is logged and swallowed (the workbook is the deliverable, the chart is decoration).
- **Data Dictionary** sheet defines every derived/ambiguous column (including the different anchors behind "age days" vs "completion days").
- **Report Info** sheet carries the filter set and the suppression notice.

### Panels covered (both formats, under the same filters)

Referral funnel, cases over time, gender, age group, vulnerability, client type, city/municipality, agency workload, referrals by agency, employment occupation, overdue referrals, most requested service.

## 4. Privacy Controls

### Small-cell suppression

Gender, age band, vulnerability, client type, and previous country of employment are special-category personal data. Buckets below `suppression_threshold` (default **5**, via `REPORTS_SUPPRESSION_THRESHOLD` in `config/reports.php`) are withheld from charts and tables in both formats; zero buckets are published. With **complement suppression**: when exactly one bucket would be withheld, the next-smallest non-zero bucket is withheld too, so the hidden value cannot be recovered by subtracting from the printed total.

Suppression is reported on the cover, the Report Info sheet, and the provenance page — never silent. It applies to **all roles including ADMIN**. A role-conditional privacy rule is the first thing an assessor pulls on; an unsuppressed export must be a separate, separately-audited capability, not a flag.

### Export audit trail

Every export writes `AuditAction::EXPORT` rows against the data-export module at **attempt and again at outcome** (`COMPLETED` or `BLOCKED`), joined by one correlation ID per request. Recorded: actor, role, format, complete filter set, matched volumes, suppression status, IP, user agent, correlation ID. The PDF renders via `output()` *before* the outcome row is logged, so a render failure is never recorded as `COMPLETED`. The audit payload contains no client-identifying data, because the exports do not.

### Pre-flight guards

Exports are synchronous (256M memory / 60s time budgets apply). An unservable range is refused before any work starts, with a message naming the matched volume and the limit and suggesting how to narrow it.

| Format | Limit (`config/reports.php`) | Basis |
|--------|------------------------------|-------|
| Excel | `export_row_cap` = **6,000** rows (`REPORTS_EXPORT_ROW_CAP`) | Measured 8.2s / 188 MB at cap — ~75% of memory, <15% of time |
| PDF | **30,000** rows (backstop) | Comfortably measured at ~24k referrals (8.0s / 194 MB); a backstop above the highest measured volume, not a tuned limit — raise only with a fresh measurement |

Deliberately **not** done: raising `memory_limit`/`max_execution_time` at runtime. Documented graceful refusal beats a 60-second failure. Streaming work remains the prerequisite if the Excel cap must ever rise.

## 5. Related Export Surfaces (Not These Routes)

- `GET /cases/export-excel`, `/clients/export-excel`, `/referrals/export-excel`, `/feedbacks/export-excel`, and their `export-count` companions share `DataExportService` (conditional-formatting banding, sampled column widths, streamed download). Column-width/style changes there affect all of them.
- `GET /admin/data-export/export` (`DataExportController`) is the admin dataset export — separate route, separate audit.
- `GET /audit-logs/export` (`AuditLogController@export`) is admin-only (controller-enforced), capped at `AUDIT_EXPORT_MAX_ROWS` (**100,000**) with strict date validation and formula-injection neutralization.
- `/api/readyz` includes an `image_rendering` check, and the image build asserts GD/FreeType capability plus a real chart render — the regression net for the original PDF outage class. After deploy, confirm `image_rendering: ok` and run one real PDF export.

## 6. Open Item (Requires Human Sign-Off)

The exports carry demographic and vulnerability aggregates. The DPIA and records of processing must reflect this disclosure surface — needs the DPO's review before the change is treated as compliant. Not auto-generatable.

## 7. Verification

```bash
php artisan test --filter ReportsExportControls   # 16 tests: suppression + complement rule, role uniformity, pre-flight limits, blocked messaging, attempt/block/completion audit rows, section coverage, appendix bounding, agency scoping, cell typing, data dictionary
php artisan test --filter PdfChartRenderer         # fallback rendering with FreeType forced off
```

Still needs a human: visual inspection of all three role PDFs (page breaks, footers, chart legibility, cover layout); Excel chart rendering confirmed in Excel/LibreOffice (PhpSpreadsheet writes the XML, only the apps confirm it); production `/api/readyz` + one real export after deploy.
