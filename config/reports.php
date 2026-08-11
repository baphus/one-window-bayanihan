<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Export limits
    |--------------------------------------------------------------------------
    |
    | These numbers are measured, not chosen. Production runs PHP with
    | memory_limit=256M and max_execution_time=60 (docker/php/php.ini), behind
    | nginx with fastcgi_read_timeout 60 (docker/nginx/conf.d/default.conf).
    | Exports are generated synchronously inside the request, so both ceilings
    | apply to a single export.
    |
    | Measured on 12,000 cases / 23,896 referrals, per detail sheet, after the
    | conditional-formatting and explicit-width rewrite of DataExportService:
    |
    |   rows    time     peak memory
    |   1,000   2.4s     154 MB
    |   2,500   3.8s     130 MB
    |   5,000   7.1s     172 MB
    |  10,000  15.0s     250 MB   <- 98% of the 256M limit
    |
    | (Before that rewrite the same curve read 12.7s / 29.6s / 66.7s and then
    | an outright OOM at 10,000 — the 5,000-row case alone exceeded the 60s
    | request timeout.)
    |
    | The cap is set to leave roughly 25% memory headroom rather than run to
    | the edge, because peak memory also carries the report payload, the
    | session, and whatever else the request touched. 6,000 rows sits near
    | 190 MB — about 74% of budget — and well inside the time ceiling.
    |
    */

    'export_row_cap' => (int) env('REPORTS_EXPORT_ROW_CAP', 6000),

    /*
    | Pre-flight guard. Above this many matching rows the export is refused
    | with an actionable message instead of being attempted and failing, or
    | being silently truncated. Kept equal to the cap so a user is always told
    | when their range is too wide rather than handed a partial file.
    */

    'export_preflight_max_rows' => (int) env('REPORTS_EXPORT_PREFLIGHT_MAX_ROWS', 6000),

    /*
    | The PDF is bounded by design — a fixed set of charts plus a capped
    | appendix — so it scales far better than the workbook. Measured at 12,000
    | cases / 24,000 referrals it renders in 8.4s at 184 MB, well inside both
    | ceilings. This threshold sits above the highest volume actually measured;
    | it is a backstop against a range nobody has tested, not a tuned limit.
    | Raise it only alongside a fresh measurement.
    */

    'pdf_preflight_max_rows' => (int) env('REPORTS_PDF_PREFLIGHT_MAX_ROWS', 30000),

    /*
    | The PDF is a presentation document, not a ledger. It carries an appendix
    | of the highest-risk rows and points at the Excel export for the full set.
    | Measured at 12,000 cases the PDF renders in 5.5s at 146 MB, so its
    | ceiling is far looser than Excel's — but it is bounded rather than
    | unbounded, and the page states what was omitted.
    */

    'pdf_appendix_rows' => (int) env('REPORTS_PDF_APPENDIX_ROWS', 200),

    'pdf_top_n' => (int) env('REPORTS_PDF_TOP_N', 10),

    /*
    | Charts with one row per category get unreadable past this many
    | categories, so they show the top N and the accompanying table carries
    | the full list.
    */

    'chart_max_categories' => (int) env('REPORTS_CHART_MAX_CATEGORIES', 15),

    /*
    |--------------------------------------------------------------------------
    | Small-cell suppression
    |--------------------------------------------------------------------------
    |
    | Gender, age band, vulnerability, client type and employment country are
    | special-category personal data under the Data Privacy Act. Aggregates
    | below this threshold are suppressed in every export, for every role
    | including ADMIN, so a small filtered cohort cannot be re-identified from
    | a downloaded file.
    |
    */

    'suppression_threshold' => (int) env('REPORTS_SUPPRESSION_THRESHOLD', 5),

    'suppressed_sections' => [
        'genderDistribution',
        'ageGroupDistribution',
        'vulnerabilityDistribution',
        'clientTypeDistribution',
        'employmentDistribution',
    ],

];
