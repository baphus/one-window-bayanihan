<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class CaseSummaryPdfTest extends TestCase
{
    public function test_summary_keeps_case_page_facts_on_one_a4_page(): void
    {
        $case = new CaseFile([
            'case_number' => 'CASE-2026-001', 'status' => 'OPEN', 'tracker_number' => 'TRACK-001',
            'first_name' => 'Maria', 'last_name' => 'Reyes', 'client_type' => 'OFW',
            'employment_status' => 'RETURNED', 'deployment_mode' => 'DIRECT_HIRED', 'return_reason' => 'TERMINATION',
            'summary' => str_repeat('Client requests assistance with unpaid wages. ', 200),
        ]);
        $case->setRelation('user', (object) ['name' => 'Case Manager']);
        $case->setRelation('reviewer', (object) ['name' => 'Case Manager']);
        $case->setRelation('categories', collect());
        $referrals = collect(range(1, 8))->map(fn ($i) => (object) [
            'agency' => (object) ['name' => 'Partner Agency '.$i],
            'category' => (object) ['name' => 'Legal & Rights Advocacy'],
            'concern' => 'Unpaid wages',
            'required_services' => str_repeat('Legal assistance ', 50),
            'status' => 'PENDING', 'updated_at' => now(),
            'decision' => null, 'decision_comment' => null,
        ]);

        $html = view('pdf.case-report', [
            'case' => $case, 'referrals' => $referrals, 'latestEvent' => null,
            'documentAnnexes' => [], 'exportedAt' => '31 Oct 2026',
        ])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $pdf->render();

        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertEqualsWithDelta(595.28, $pdf->getDomPDF()->getCanvas()->get_width(), 0.1);
        $this->assertStringContainsString('Deployment mode', $html);
        $this->assertStringContainsString('Employment status', $html);
        $this->assertStringContainsString('Return reason', $html);
        $this->assertStringContainsString('Returned', $html);
        $this->assertStringContainsString('Direct Hired', $html);
        $this->assertStringContainsString('Termination', $html);
        $this->assertStringContainsString('Partner Agency 4', $html);
        $this->assertStringNotContainsString('Partner Agency 8', $html);
        $this->assertStringContainsString('more referrals in case workspace', $html);
    }
}
