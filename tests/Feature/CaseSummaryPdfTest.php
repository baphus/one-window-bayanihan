<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class CaseSummaryPdfTest extends TestCase
{
    public function test_summary_is_one_a4_page_with_long_text_and_agencies(): void
    {
        $case = (object) [
            'case_number' => 'CASE-2026-001', 'status' => 'OPEN',
            'tracker_number' => 'TRACK-001', 'created_at' => now(), 'closed_at' => null,
            'user' => (object) ['name' => 'Case Manager'],
            'categories' => collect([(object) ['name' => 'Legal Assistance']]),
            'caseIssue' => (object) ['name' => 'Unpaid wages'],
            'vulnerability_indicator' => 'Displaced worker',
            'summary' => str_repeat('Client requests assistance with unpaid wages. ', 200),
        ];
        $client = (object) [
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Reyes',
            'suffix' => null, 'type' => 'OFW', 'date_of_birth' => '1990-01-01',
            'sex' => 'FEMALE', 'contact_number' => '09171234567', 'email' => 'maria@example.test',
        ];
        $referrals = collect(range(1, 8))->map(fn ($i) => (object) [
            'agency' => (object) ['name' => 'Partner Agency '.$i],
            'required_services' => str_repeat('Legal assistance ', 50),
            'status' => 'PENDING', 'updated_at' => now(),
        ]);
        $data = [
            'case' => $case, 'client' => $client, 'address' => (object) ['street' => '123 Main Street', 'barangay' => 'Central', 'city_municipality' => 'Cebu City', 'province' => 'Cebu', 'region' => 'Region VII'],
            'employment' => (object) ['employer_name' => 'Overseas Employer', 'position' => 'Caregiver', 'country' => 'Japan', 'start_date' => '2020-01-01', 'end_date' => null, 'date_of_arrival' => '2026-01-01'],
            'referrals' => $referrals, 'latestEvent' => null, 'exportedAt' => '31 Oct 2026',
            'textLimit' => 100,
            'avatar' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0WQAAAAASUVORK5CYII=',
        ];

        $html = view('pdf.case-report', $data)->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $pdf->render();

        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertEqualsWithDelta(595.28, $pdf->getDomPDF()->getCanvas()->get_width(), 0.1);
        $this->assertEqualsWithDelta(841.89, $pdf->getDomPDF()->getCanvas()->get_height(), 0.1);
        $this->assertStringContainsString('Partner Agency 8', $html);
        $this->assertStringContainsString('See full case record', $html);
        $this->assertStringNotContainsString('Next of Kin', $html);
        $this->assertStringNotContainsString('Activity Timeline', $html);
    }
}
