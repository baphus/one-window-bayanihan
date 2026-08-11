# Manual QA Test Cases — Reports Export (PDF & Excel)

> **Version:** 1.0.0
> **Date:** 2026-08-11
> **Scope:** Addendum to `MANUAL_QA_TEST_CASES.md` §12 (Reports & Analytics), covering the rebuilt PDF and Excel exports
> **Design record:** `REPORTS_EXPORT_DESIGN_v1.0.0.md`

---

## Changelog

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-08-11 | Initial addendum. Covers the GD/FreeType outage fix, Excel memory remediation, twelve added sections, small-cell suppression, export audit trail, pre-flight guards, and the PDF rebuild. |

---

## Why these cases exist

Most of what follows is **automated** in `ReportsExportControlsTest` and
`PdfChartRendererTest`. The cases below are the ones automation cannot settle:
anything requiring a human to *look* at the artefact, and anything that only
manifests in the built container image.

Each row marks whether it is already covered by an automated test. Do not skip
the manual ones on the strength of a green suite — the outage this work fixed
passed every automated test in the repository.

---

## 1. Production outage regression (highest priority)

| ID | Test Case | Steps | Expected Result | Role | Auto? |
|---|---|---|---|---|---|
| EXP-001 | PDF export completes | 1. Open `/reports` 2. Set any date range 3. Click **Export PDF** | A `.pdf` downloads. No 500, no error page. | CM, Admin, Agency | Partial |
| EXP-002 | Charts carry readable labels | 1. Open the downloaded PDF 2. Inspect the pie and bar charts | Axis and legend labels are crisp TrueType text, not blocky bitmap glyphs. Blocky text means FreeType is still missing — the export works but the image is wrong. | Admin | No |
| EXP-003 | Readiness probe reports image rendering | `GET /api/readyz` with `X-Monitoring-Token` | `checks.image_rendering.status = "ok"` and `freetype: true`. A `fail` here must block release. | n/a | No |
| EXP-004 | Build asserts GD capability | Run the **Build and Push Image** workflow | "Verify GD has FreeType and JPEG support" and "Verify a report chart renders inside the image" both pass. Deliberately break the Dockerfile flag once to confirm the job fails. | n/a | No |

---

## 2. PDF presentation — requires human eyes

| ID | Test Case | Steps | Expected Result | Role | Auto? |
|---|---|---|---|---|---|
| EXP-010 | Cover page | Open page 1 | Title, department, full filter set, generated-by/at, and the confidentiality notice all present and legible. | All | No |
| EXP-011 | Page numbering | Scroll the whole document | Every page shows "Page N" in the footer. **No literal `{PAGENO}` or `{nbpg}` anywhere** — that was the previous defect. | All | No |
| EXP-012 | Footer repeats | Check first, middle and last pages | The department/confidentiality footer appears on every page, not just once. | All | No |
| EXP-013 | Page breaks | Review each section heading | No heading is orphaned at the foot of a page; no table is split with its header stranded. | All | No |
| EXP-014 | Typography | Read the smallest text | Nothing is uncomfortably small (floor is 8.5px). Headings step down consistently. | All | No |
| EXP-015 | Charts sit beside their tables | Inspect each chart row | Chart and companion table align; no chart overflows the page width. | All | No |
| EXP-016 | Long category lists capped | Filter to data with >15 cities | Chart shows the top 15; subtitle states the cap; the table lists every city. | Admin | No |
| EXP-017 | Filipino characters render | Use data with ñ / diacritics in agency or city names | Characters render correctly in both chart labels and tables. | All | No |
| EXP-018 | Empty state | Set a date range with no matching data | Document still renders. Sections with no data are omitted or show an explicit empty message — no broken layout, no error. | All | Partial |

---

## 3. PDF content correctness

| ID | Test Case | Steps | Expected Result | Role | Auto? |
|---|---|---|---|---|---|
| EXP-020 | Figures match the screen | Note the KPIs on `/reports`, then export with identical filters | Every KPI in the PDF matches the screen exactly. | CM, Admin | No |
| EXP-021 | Category counts now match | Compare the Categories panel on screen against the PDF | They agree. Before this change the panel was all-time and disagreed with every other figure. **Expect these counts to be lower than users remember.** | CM, Admin | No |
| EXP-022 | Client type / vulnerability / case status / employment agree | Compare each panel on screen against the export | All agree with the active date range. Same expectation of lower numbers as EXP-021. | Admin | No |
| EXP-023 | Twelve added sections present | Review the PDF against the Reports page tabs | Funnel, cases over time, gender, age group, vulnerability, client type, city, agency workload, referrals by agency, occupation, overdue, most requested service all appear. | Admin | Yes |
| EXP-024 | Appendix bounded and honest | Export a range with >200 active referrals | Appendix shows 200 rows and states "showing 200 of N", pointing at the Excel export. | Admin | Yes |
| EXP-025 | Provenance page | Open the last section | Schema version, timestamps, generated-by, matched row counts, appendix limit, suppression status all present. | All | No |

---

## 4. Excel workbook

| ID | Test Case | Steps | Expected Result | Role | Auto? |
|---|---|---|---|---|---|
| EXP-030 | Workbook opens cleanly | Export and open in Excel (not LibreOffice) | No repair prompt, no corruption warning. | All | No |
| EXP-031 | Numbers are numbers | Select the `Age Days` column on **Referral Details** | Excel's status bar shows Sum/Average. If it shows only Count, the column is still text. | Admin | Partial |
| EXP-032 | Dates sort chronologically | Sort **Case Details** by `Created At` | Chronological order, not lexicographic. | Admin | No |
| EXP-033 | Percentages | Check `Share` on **Referral Funnel** and `Completion Rate` on **Executive Summary** | Display as percentages and behave as numbers in a formula. | Admin | Partial |
| EXP-034 | Identifiers intact | Inspect `Referral ID` / `Case ID` | Full UUIDs as text — no scientific notation, no truncation. | Admin | Yes |
| EXP-035 | Native charts render | Open the nine headline sheets | Each shows a chart bound to its cells. Edit a value and confirm the chart updates. | Admin | No |
| EXP-036 | Row banding and status colours | Scroll a detail sheet | Alternating row shading present; status cells coloured by value. (Now conditional formatting — confirm it survived.) | Admin | No |
| EXP-037 | Data Dictionary | Open the **Data Dictionary** sheet | Defines every derived column, including the different anchors for Age Days and Completion Days. | All | Yes |
| EXP-038 | Sheet order mirrors the PDF | Compare tab order to PDF section order | They correspond. | All | No |
| EXP-039 | Formula injection guard | Create a case with a summary starting `=cmd`, export | The cell renders as literal text; Excel does not treat it as a formula. | Admin | No |

---

## 5. Limits and guards

| ID | Test Case | Steps | Expected Result | Role | Auto? |
|---|---|---|---|---|---|
| EXP-040 | Oversized Excel export refused | Set `REPORTS_EXPORT_PREFLIGHT_MAX_ROWS=10`, export a wider range | Redirected back with an error naming the matched count and the limit, and suggesting how to narrow. **No 500.** | Admin | Yes |
| EXP-041 | PDF limit is separate | Same data, export PDF | Succeeds — the PDF threshold is independent and higher. | Admin | Yes |
| EXP-042 | Detail sheets capped with warning | Export a range exceeding `export_row_cap` | Report Info records the cap and a warning states how many rows were omitted. Nothing is silently truncated. | Admin | Partial |
| EXP-043 | Timing under load | Export the widest permitted range on production-like data | Completes inside 60s. Record the actual time; if it exceeds ~30s, revisit the threshold. | Admin | No |
| EXP-044 | Concurrent exports | Two users export large ranges simultaneously | Both complete. Watch container memory — the 256M limit is per request but the container is shared. | Admin | No |

---

## 6. Privacy controls

| ID | Test Case | Steps | Expected Result | Role | Auto? |
|---|---|---|---|---|---|
| EXP-050 | Small buckets withheld | Filter to a narrow cohort producing a demographic bucket of 1–4 | That bucket does not appear in the chart or table; the suppression notice appears on the cover and Report Info. | Admin | Yes |
| EXP-051 | Admin is not exempt | Repeat EXP-050 as ADMIN | Suppression still applies. | Admin | Yes |
| EXP-052 | Zero buckets still shown | Find a demographic bucket with zero | Shown as 0, not withheld. | Admin | Yes |
| EXP-053 | No client PII in detail rows | Search the workbook for a known client name, tracker number or case summary | Not present anywhere. | Admin | Yes |
| EXP-054 | Agency scope not widened | Export as an Agency user | Geography, Cities, Agency Workload, Referrals by Agency, Vulnerability, Case Issues sheets absent. Only that agency's data present. | Agency | Yes |

---

## 7. Audit trail

| ID | Test Case | Steps | Expected Result | Role | Auto? |
|---|---|---|---|---|---|
| EXP-060 | Successful export logged | Export, then open `/audit-logs` | Two EXPORT entries: `ATTEMPTED` and `COMPLETED`. | Admin | Yes |
| EXP-061 | Blocked export logged | Trigger EXP-040, then check audit | `ATTEMPTED` and `BLOCKED` recorded. | Admin | Yes |
| EXP-062 | Filters captured | Export with a specific range, scope, province and agency | The audit record's filter set matches exactly. | Admin | Yes |
| EXP-063 | Audit is PII-free | Inspect the audit payload | Filters, counts and outcome only — no client data. | Admin | No |

---

## Sign-off

| Area | Tester | Date | Result |
|---|---|---|---|
| §1 Outage regression | | | |
| §2 PDF presentation | | | |
| §3 PDF content | | | |
| §4 Excel workbook | | | |
| §5 Limits and guards | | | |
| §6 Privacy controls | | | |
| §7 Audit trail | | | |

**Release gate:** §1 and §6 must pass before deploy. §2 requires a human to open
and read all three role PDFs — no automated check substitutes for it.
