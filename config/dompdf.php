<?php

// Trimmed published config for barryvdh/laravel-dompdf.
//
// Every value in the shipped vendor default
// (vendor/barryvdh/laravel-dompdf/config/dompdf.php) is used as-is: the
// package's ServiceProvider merges those defaults automatically via
// mergeConfigFrom(), so this file lists no overrides. Keep it empty unless
// the app needs a value that differs from the vendor default.
//
// Consumer notes (do not break these when editing):
// - App\Services\Reports\PdfChartRenderer embeds charts as
//   data:image/png;base64 URIs, which requires the data:// entry under
//   options.allowed_protocols (currently provided by the vendor default).
// - Pdf::loadView() callers (GenerateSystemReport, ReportsController,
//   CaseController) rely on the vendor default paper (a4, portrait).
//
// NOTE: mergeConfigFrom() merges only top-level keys, so an `options` key
// added here would REPLACE the vendor `options` array wholesale. Never add
// a partial `options` array — keep it whole or omit it entirely.

return [];
