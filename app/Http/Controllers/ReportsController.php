<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiService;
use App\Services\ReportsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService,
        private readonly AiService $aiService,
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

    public function aiInsight(Request $request)
    {
        $user = $request->user();
        $fromDate = $request->input('from');
        $toDate = $request->input('to');

        $data = $this->reportsService->getAll(
            userId: $user->id,
            role: $user->role,
            fromDate: $fromDate,
            toDate: $toDate,
        );

        $summary = [
            'role' => $user->role,
            'period' => $fromDate && $toDate ? "$fromDate to $toDate" : 'Last 12 months',
            'kpis' => $data['kpis'] ?? [],
            'overview' => $data['overview'] ?? [],
            'geographicDistribution' => $data['geographicDistribution'] ?? [],
            'employmentDistribution' => $data['employmentDistribution'] ?? [],
            'caseStatusDistribution' => $data['caseStatusDistribution'] ?? [],
            'referralStatusDistribution' => $data['referralStatusDistribution'] ?? [],
            'categoryDistribution' => $data['categoryDistribution'] ?? [],
        ];

        $prompt = 'Here is the report data: '.json_encode($summary)."\n\nProvide a concise business insight summary (max 3 paragraphs) highlighting key metrics, trends, and actionable recommendations.";

        $provider = $this->aiService->getProvider();

        if (! $provider || ! $provider->isConfigured()) {
            return response()->json(['insight' => null, 'error' => 'AI service is not configured. Configure it in System Settings.']);
        }

        try {
            $insight = $provider->sendMessage($prompt, [
                'system_prompt' => 'You are a senior business intelligence analyst for the Department of Migrant Workers (DMW) Region VII. Analyze the report data and provide actionable insights. Be specific with numbers, identify trends, and suggest improvements. Keep responses to 3 paragraphs maximum.',
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

            return response()->json(['insight' => $insight]);
        } catch (\Exception $e) {
            return response()->json(['insight' => null, 'error' => 'AI analysis failed: '.$e->getMessage()]);
        }
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
