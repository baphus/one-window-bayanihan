<?php

namespace App\Http\Controllers;

use App\Services\ReportsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->query('from');
        $toDate = $request->query('to');

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        $data['managedReferrals'] = $this->reportsService->getManagedReferrals(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        return Inertia::render('Reports/Index', $data);
    }

    public function exportPdf(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->query('from');
        $toDate = $request->query('to');

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        $data['generatedAt'] = now()->format('Y-m-d H:i:s');
        $data['generatedBy'] = $user->name;

        $pdf = Pdf::loadView('pdf.report', $data);

        return $pdf->download('bayanihan-report-'.now()->format('Ymd-His').'.pdf');
    }
}
