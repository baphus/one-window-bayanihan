# Reports Export — Design and Measurement Record

**Version:** 1.0.0
**Status:** Implemented, pending QA sign-off
**Scope:** `GET /reports/export-pdf`, `GET /reports/export-excel` (Case Manager, Admin, Agency)
**Supersedes:** nothing — this is the first design record for this subsystem.

---

## Changelog

| Version | Date | Author | Change |
|---|---|---|---|
| 1.0.0 | 2026-08-11 | Engineering | Initial record. Documents the production PDF outage and its root cause, the Excel memory/timeout defect, the five unfiltered report panels, and the design of the rebuilt exports (sections, suppression, audit, guards, typography). Carries the measurement evidence behind every configured limit. Includes the independent review findings and their remediation (§8). |

---

## 1. Why this document exists

Every numeric limit in `config/reports.php` is derived from a measurement recorded
here rather than chosen by judgement. If a limit is changed, the measurement that
justifies the new value belongs in this file alongside the old one. This is the
artefact to hand an ISO 9001 or SOC 2 assessor who asks why the export cap is the
number it is.

---

## 2. Defects found and fixed

### 2.1 Reports PDF export returned 500 for every user (production outage)

**Symptom.** `GET /reports/export-pdf` returned HTTP 500 on `dmw7.owbap.app`.

**Root cause.** `Call to undefined function App\Services\Reports\imagettftext()` at
`PdfChartRenderer.php:392`, reached from `pieChart()` via the first chart in
`report.blade.php`. `imagettftext()` and `imagettfbbox()` exist only when GD is
compiled with FreeType. The image ran `docker-php-ext-install gd` with no
`docker-php-ext-configure gd --with-freetype`, and without `libfreetype6-dev`
installed. GD loaded and reported itself present; only the TrueType half was
missing. `imagefilledarc()` and `imageellipse()` worked, which is why the trace
reaches the legend loop before dying.

**Why nobody caught it.**

- `/up` never touches the feature and answered 200 throughout.
- `/api/readyz` checked database, scheduler, mail and queue — not image rendering.
- The PHPUnit suite passed, because every CI runner and developer machine has
  FreeType. The bug is invisible above the image layer.
- Reproduction attempts on a developer machine were negative across 16 filter
  permutations, two dataset sizes (1 case and 12,000 cases), a 256M memory cap,
  and `allow_url_fopen=0`. The defect is a build-configuration gap, not a data
  or code-path gap.

**Duration.** The Dockerfile GD line was last touched 2026-06-24 (`822aba2`).
Charts were added to the report on 2026-08-02 (`34054ce`). Every reports PDF
export failed from 2026-08-02 until the fix.

**Fix — three layers, because one was not enough.**

1. `Dockerfile` — `libfreetype6-dev`, `libjpeg62-turbo-dev`, and
   `docker-php-ext-configure gd --with-freetype --with-jpeg` before install.
2. `PdfChartRenderer::hasTrueType()` guard with a GD bitmap-font fallback, so a
   missing build flag costs label quality rather than the entire feature.
   Verified end to end: with the capability forced off, the full report renders.
3. `/api/readyz` gained an `image_rendering` check, and the image build gained two
   assertions (`gd_info()` flags, plus a real chart render through
   `PdfChartRenderer`). The build assertion is the only layer where this class of
   defect is detectable.

### 2.2 Excel export exceeded both production ceilings

Production limits — `docker/php/php.ini`: `memory_limit = 256M`,
`max_execution_time = 60`; `docker/nginx/conf.d/default.conf`:
`fastcgi_read_timeout 60`. Exports run synchronously inside the request, so both
apply to a single export.

`DataExportService::writeDataRow()` called `getStyle($cellRef)->getFill()` per
cell — roughly 100,000 style objects for a 10,000-row detail sheet — and
`populateSheet()` set `setAutoSize(true)` on every column, forcing a measure pass
over every cell at save time.

**Measured, per detail sheet, on 12,000 cases / 23,896 referrals:**

| Rows | Before | After |
|---|---|---|
| 1,000 | 12.7s / 106 MB | 2.4s / 154 MB |
| 2,500 | 29.6s / 130 MB | 3.8s / 130 MB |
| 5,000 | 66.7s / 214 MB | 7.1s / 172 MB |
| 6,000 | not measured | 8.5s / 182 MB |
| 10,000 | **out of memory** | 15.0s / 250 MB |

The prior configuration capped detail sheets at 10,000 rows, so the configured
maximum was itself unservable, and 5,000 rows already exceeded the 60s timeout.

**Fix.** Row banding and status colours became sheet-level conditional formatting
(one rule per sheet, one per status value) and column widths are computed from a
200-row sample instead of autosizing. Output is visually unchanged.

**Residual risk.** Time is comprehensively fixed. Memory is not: profiling splits
the 10,000-row figure into ~92 MB of fetched rows and ~158 MB of PhpSpreadsheet
cell storage, and the latter scales with row count regardless of styling. At
10,000 rows the margin against the 256 MB limit is ~6 MB, which is one schema
change away from regression.

**Decision.** `export_row_cap` set to **6,000** (8.5s, 182 MB — 71% of the memory
budget, 14% of the time budget). This lowers the maximum exportable detail rows
from 10,000. If 10,000 is required, the streaming work deferred in Q18-c must be
done first; raising the cap alone would reinstate the defect.

### 2.3 Five report panels ignored the active filters

`categoryDistribution`, `getClientTypeDistribution`, `getVulnerabilityDistribution`,
`getCaseStatusDistribution`, `getLastEmploymentDistribution` and
`getEmploymentOccupationBreakdown` took no date or geography arguments. They
reported all-time, unfiltered figures beside panels that honoured the filters, on
screen and in the export. Reconciling an exported category count against the
dashboard did not add up.

Fixed via `ReportsService::applyCaseWindow()`, which applies the date window and
applies geography as a **subquery rather than a join** — the existing
`applyGeoFilter()` join would count a client with two addresses twice and inflate
every `count(*)`.

**User-visible consequence:** figures in these panels will drop for users who had
been reading all-time numbers. This is a correction, not a regression.

### 2.4 mPDF syntax in a dompdf document

`report.blade.php` used `@page { footer: page-footer }`, `<htmlpagefooter>` and
`Page {PAGENO} of {nbpg}`. dompdf ignores all three: the footer table fell into
the body flow on the last page and the document printed a literal
`{PAGENO} of {nbpg}`. Replaced with a `position: fixed` footer and
`content: counter(page)`, which dompdf supports.

### 2.5 Overdue referrals eager-loaded client records

`ReportsService::getOverdueReferrals()` eager-loads `caseFile.client`. Reusing it
in the export would have pulled client PII into a document that deliberately
carries none, and it takes no date range so its figure would not match the window
printed on the page. The export computes the count from its own filtered base.

---

## 3. What the exports now contain

Twelve sections rendered on the Reports page but were absent from both exports:
referral funnel, cases over time, gender, age group, vulnerability, client type,
city/municipality, agency workload, referrals by agency, employment occupation,
overdue referrals, most requested service. All are now included, under the same
filters, in both formats.

### 3.1 PDF

Presentation document, not a ledger.

- **Cover page** — title, full filter set, generation provenance, and a
  confidentiality marking (ISO 27001 A.5.13, information labelling).
- **Typography** — DejaVu Sans (dompdf's bundled Unicode face; carries Filipino
  diacritics) on an 18/13/11/9.5/8.5 scale. Nothing renders below 8px; the
  previous template bottomed out at 7px.
- **Charts** — PNGs from `PdfChartRenderer`; dompdf drops inline SVG geometry and
  leaks the `<text>` nodes into the page. Chart type per section: line for time
  series, horizontal bar for long label sets, vertical bar for ordered bands,
  pie for two-to-five-way splits.
- **Long category lists** are charted to the top 15 (`chart_max_categories`) with
  the full set in the companion table, and the chart subtitle states what was
  capped.
- **Appendix** — the 200 highest-risk active referrals and cases, continuing the
  same ranking as the summary tables, each stating "showing N of M" and pointing
  at the Excel export for the remainder. No silent truncation anywhere.
- **Provenance page** — schema version, generation timestamps, matched row counts,
  appendix limit, and the suppression status.

Measured at 12,000 cases / 23,896 referrals: **8.4s, 184 MB, 29 pages.**

### 3.2 Excel

Machine-readable counterpart, 27 sheets, one per section, in the PDF's order.

- **Real cell types.** Counts are `int`, rates are `percent`, timestamps are
  `datetime`. Previously every column was declared `string` and written as
  `TYPE_STRING`, so the entire workbook was text — unsortable, unsummable, not
  pivotable. Identifiers remain forced text so Excel cannot convert them to
  scientific notation, and the formula-injection guard on text cells is retained.
- **Native charts** on the nine headline sheets, bound to their cells so they
  survive filtering. Skipped above 60 categories, and a chart failure is logged
  and swallowed — the workbook is the deliverable, the chart is decoration.
- **Data Dictionary sheet** defining every derived or ambiguous column, including
  the different anchors behind "age days" and "completion days".
- **Report Info sheet** carries the filter set and the suppression notice.

---

## 4. Privacy controls

### 4.1 Small-cell suppression

Gender, age band, vulnerability, client type and previous country of employment
are special-category personal data under the Data Privacy Act. In a narrowly
filtered export a bucket of one or two is re-identifiable.

Buckets below `suppression_threshold` (default **5**) are withheld from the chart
series and the table in both formats. Zero buckets are published — an empty
bucket identifies nobody. Suppression is reported on the cover, the Report Info
sheet, and the provenance page rather than silently shortening a chart.

**Applies to ADMIN as well as every other role.** A role-conditional privacy rule
is the first thing an assessor pulls on. If an unsuppressed export is ever
required it should be a separate, separately-audited capability, not a flag.

### 4.2 Export audit trail

Every export writes `AuditAction::EXPORT` rows against
`AuditModule::DATA_EXPORT` at **attempt** and again at **outcome**
(`COMPLETED` or `BLOCKED`). Recorded: actor, role, format, the complete filter
set, matched volumes, suppression status, IP, user agent, correlation ID.

Logging the attempt rather than only the success is what lets the trail answer
"who tried to pull this data" — the question that matters after an incident
(SOC 2 CC7.2, ISO 27001 A.8.15). Before this change neither export route wrote
any audit record at all.

The audit payload contains no client-identifying data, because the exports do not.

### 4.3 Role scoping

An export must never widen a role's visibility beyond its own dashboard. Agency
exports drop the sheets and payload sections an agency cannot see on screen
(`AGENCY_HIDDEN_SHEETS`, `AGENCY_HIDDEN_SECTIONS`), asserted by test.

---

## 5. Pre-flight guards

Exports are synchronous. A range that cannot be served is refused before any work
starts, with a message naming the matched volume and the limit and suggesting how
to narrow it. Previously such a range produced an unexplained 500.

| Format | Limit | Basis |
|---|---|---|
| Excel | 6,000 rows | Measured: 8.5s / 182 MB at 6,000. See §2.2. |
| PDF | 30,000 rows | Measured comfortably at 23,896 referrals (8.4s / 184 MB). Set as a backstop above the highest volume actually measured — **not** a tuned limit. Raise only with a fresh measurement. |

Deliberately **not** done: raising `memory_limit` or `max_execution_time` at
runtime. Bumping limits to cover unbounded work converts a 5-second failure into
a 60-second one; a documented, graceful degradation path is what an availability
commitment needs as evidence.

---

## 6. Standards-readiness

| Standard | Control | How this work addresses it |
|---|---|---|
| ISO 27001 | A.5.13 Labelling of information | Confidentiality marking on the PDF cover |
| ISO 27001 | A.8.11 / A.8.12 Data masking and leakage prevention | Small-cell suppression; no PII in detail rows; overdue count computed without the client eager-load |
| ISO 27001 | A.8.15 Logging | Export attempt and outcome audit rows |
| ISO 27001 | A.8.31 Separation of environments | Build-time image assertions for GD/FreeType |
| SOC 2 | CC7.2 Monitoring | Readiness probe covers image rendering; blocked exports recorded |
| SOC 2 | CC8.1 Change management | Image capability asserted in CI before push |
| ISO 9001 | Evidence-based decisions | Every configured limit traced to the measurement in this document |
| DPTM | Data protection policy / accountability | Suppression rule applied uniformly across roles and documented |

### Open item — requires human sign-off

The exports now carry demographic and vulnerability aggregates that they did not
previously contain. **The DPIA and records of processing need updating to reflect
the new disclosure surface.** This is not auto-generatable and needs the DPO's
review before the change is treated as compliant.

---

## 7. Verification performed

- Full PHPUnit suite (see QA addendum for the run record).
- `ReportsExportControlsTest` — 16 tests covering suppression thresholds and role
  uniformity, pre-flight limits per format, blocked-export messaging, audit rows
  for attempt/block/completion and filter capture, section coverage, appendix
  bounding, agency scoping, cell typing, and the data dictionary.
- `PdfChartRendererTest` — chart rendering with FreeType forced off, reproducing
  the production capability gap that no other test can see.
- Workbook round-trip: written, re-read, and asserted to carry numeric cell types
  and 9 embedded charts.
- Renders verified for ADMIN, CASE_MANAGER and AGENCY against 12,000 cases.

### Not yet verified — needs a human

- **Visual inspection of the PDF.** Page-break placement, footer position, chart
  legibility and the cover layout cannot be asserted programmatically. The three
  role PDFs should be opened and read before release.
- **Excel chart rendering in Excel itself.** PhpSpreadsheet writes the chart XML;
  only Excel and LibreOffice can confirm it renders as intended.
- **Production verification** that the GD fix took effect: `/api/readyz` should
  report `image_rendering: ok` after deploy, and a real PDF export should be run.

---

## 8. Independent review — findings and remediation

The change set was reviewed by a reviewer with no knowledge of the implementation
reasoning. Findings and their disposition:

| # | Finding | Disposition |
|---|---|---|
| 1 | Suppression defeatable by subtraction: `clientTypeDistribution` has two buckets and the same denominator is printed as "Total Cases", so withholding one bucket disclosed it exactly | **Fixed.** Complement suppression — whenever exactly one bucket is withheld, the next-smallest non-zero bucket is withheld too. Regression test added. |
| 2 | An AGENCY user with a null `agcy_id` received system-wide gender and age data: `additionalSections()` calls panel methods directly, bypassing `getAll()`'s fail-closed guard, and `filteredClientIds()` has no role check | **Fixed.** Explicit fail-closed branch returning empty sections. Regression test added. |
| 3 | The FreeType regression test was a no-op: `drawText()` called `self::hasTrueType()`, which is early-bound and ignores the subclass override — the test rendered through the normal path and asserted nothing | **Fixed.** `static::hasTrueType()`. Re-verified by confirming the overridden and non-overridden renderers now produce *different* output. This invalidated the original verification claim for the fallback. |
| 4 | Audit recorded `COMPLETED` before the document was produced — dompdf renders inside `download()`, and `generateMultiSheet()` can return 500 | **Fixed.** PDF renders via `output()` before logging; Excel logs the actual status code. |
| 5 | AGENCY exports drop `categoryDistribution` and `caseStatusDistribution`, which agencies *do* see on screen | **Fixed.** Pre-existing behaviour carried forward from the original service, on the mistaken premise recorded in its own comment. `getAgencyPayload()` returns both, agency-scoped. Both are now included in agency exports and a test asserts their presence. |
| 6 | AGENCY export printed a hard `0` for overdue referrals: the section was blanked but its sheet and KPI card still rendered | **Fixed.** Agencies do see overdue referrals on the performance tab and the count is already agency-scoped, so the section is no longer blanked. |
| 7 | 56 instances of `{{ e($x) }}` double-escaped: Blade already escapes and `e()` defaults to `doubleEncode: true`, so `&` printed as `&amp;` | **Fixed.** All 56 reduced to `{{ $x }}`. |
| 8 | Conditional-formatting priority: whole-range banding registered first would outrank status colours on even rows | **Fixed.** Status-column rules registered before the banding range. |
| 9 | `riskRows()` still fetched every active row with `->get()` and sorted in PHP to keep 200 — the same unbounded-fetch shape as the Excel defect | **Fixed.** Risk score computed in SQL, ordered and `LIMIT`ed in the database; totals from a separate `COUNT`. Regression tests cover both the ordering and the total-beyond-page. |
| 10 | Chart-write failures escape the handler: the writer runs inside the `StreamedResponse` callback, after the 200 is on the wire | **Partially fixed.** Wrapped and logged. The status code genuinely cannot change once streaming has begun; this is inherent to streamed downloads and is now recorded rather than silent. |
| — | CASE_MANAGER exports include `agencyWorkload` and `referralAgencyDistribution`, which are not on the CM dashboard | **Not changed, by decision.** The no-widening rule is specific to AGENCY, where the dashboard is the boundary of what one agency may see about the programme. Case managers already receive cross-agency scorecards and overdue queues, so agency workload is the same class of information they are trusted with. The rule and its scope are now stated explicitly in the code. |
| — | Fresh UUID per audit row prevented joining an export's attempt and outcome rows | **Fixed.** One correlation id per request. |
| — | Dead `$refCount` / `$caseCount` assignments | **Fixed.** Removed. |
| — | Column-width change affects the cases/clients/referrals/admin exports that share the service | **Accepted and documented** in the QA addendum. |
| — | Dockerfile comment inside a `RUN` line continuation | **Fixed.** Moved above the instruction so it cannot comment out the joined command. |

### Reviewer concerns checked and found sound

`applyCaseWindow()` subquery semantics and clone-safety; `employmentQuery()`
restructure; `categoryDistribution()`'s use of the JOIN variant (safe because the
aggregate is `count(DISTINCT cases.id)`); `pluck()` after explicit `select()`;
the CSV-injection guard across every text path including the new numeric types;
colour-array realignment in suppression; Blade compilation and payload-key
guarding; chart renderers' degenerate-input handling; the `back()` redirect path
through Inertia; and `recordExport()` failure behaviour.

### Post-remediation measurements (12,000 cases / 24,124 referrals, warm)

| Path | Time | Peak memory | Budget |
|---|---|---|---|
| PDF (payload + render) | 8.0s | 194 MB | 60s / 256 MB |
| Excel (6,000-row cap, 27 sheets, 9 charts) | 8.2s | 188 MB | 60s / 256 MB |

Both sit at roughly 75% of the memory ceiling and under 15% of the time ceiling.
