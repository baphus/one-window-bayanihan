<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InsightsExportController extends Controller
{
    public function exportCsv(Request $request)
    {
        $request->validate([
            'chartType' => 'nullable|string|max:100',
            'data' => 'required|array',
            'data.*' => 'required|array',
            'title' => 'nullable|string|max:255',
        ]);

        $data = $request->input('data', []);
        $title = $request->input('title', 'export');

        if (empty($data)) {
            return response()->json(['error' => 'No data to export'], 422);
        }

        $headers = array_keys($data[0]);
        $rows = collect($data)->map(fn ($row) => collect($headers)->map(fn ($h) => '"'.str_replace('"', '""', ($row[$h] ?? '')).'"')->implode(','));

        $csv = implode("\n", [implode(',', $headers), ...$rows]);

        $filename = strtolower(preg_replace('/[^a-z0-9-]/', '-', $title)).'-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $request->validate([
            'chartType' => 'nullable|string|max:100',
            'data' => 'required|array',
            'filters' => 'nullable|array',
            'title' => 'nullable|string|max:255',
        ]);

        $data = $request->input('data', []);
        $chartType = $request->input('chartType', '');
        $filters = $request->input('filters', []);
        $title = $request->input('title', 'Insights Export');

        $viewData = [
            'title' => $title,
            'chartType' => $chartType,
            'filters' => $filters,
            'headers' => ! empty($data) ? array_keys($data[0]) : [],
            'rows' => $data,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ];

        $pdf = Pdf::loadView('pdf.insights-export', $viewData);

        $filename = strtolower(preg_replace('/[^a-z0-9-]/', '-', $title)).'-'.now()->format('Ymd-His').'.pdf';

        return $pdf->download($filename);
    }
}
