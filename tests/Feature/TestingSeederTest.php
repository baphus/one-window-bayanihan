<?php

namespace Tests\Feature;

use App\Models\CaseFile;
use App\Models\User;
use App\Services\CaseNumberGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TestingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_cases_share_the_counter_with_existing_and_future_cases(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 04:00:00', 'UTC'));
        $generator = app(CaseNumberGenerator::class);
        $existing = CaseFile::factory()->withIntake()->create([
            'case_number' => $generator->nextCaseNumber(),
            'intake_date' => '2026-10-15',
            'employment_start_date' => '2020-01-01',
            'employment_end_date' => '2026-10-01',
            'employment_date_of_arrival' => '2026-10-02',
            'status' => 'OPEN',
            'consent_text_version' => 'case-intake-v1',
            'consent_method' => 'WRITTEN',
            'consent_evidence' => 'Synthetic consent',
            'confirmed_assessments' => ['LEGAL_ASSISTANCE'],
            'confirmed_assessment_by' => User::factory(),
            'confirmed_assessment_at' => now(),
        ]);

        $this->seed(TestingSeeder::class);

        $this->assertDatabaseHas('cases', ['id' => $existing->id, 'case_number' => 'OWB-202610-00001']);
        $this->assertDatabaseCount('cases', 851);
        $this->assertSame('OWB-202610-00852', $generator->nextCaseNumber());

        $this->assertFalse(Schema::hasTable('clients'));
        $this->assertSame(850, DB::table('cases')->whereNull('ofw_user_id')->where('user_id', DB::table('users')->where('email', 'case@bayanihan.gov.ph')->value('id'))->count());
        $pendingCases = DB::table('cases')->where('status', 'PENDING_REVIEW')->get();
        $this->assertCount(128, $pendingCases);
        foreach ($pendingCases as $pendingCase) {
            $this->assertSame(['passport_front_page' => 'Pending synthetic document'], json_decode(Crypt::decrypt($pendingCase->missing_document_reasons, false), true));
        }
        $approvedCase = DB::table('cases')
            ->where('status', 'OPEN')
            ->where('user_id', DB::table('users')->where('email', 'case@bayanihan.gov.ph')->value('id'))
            ->whereNotNull('reviewed_document_ids')
            ->first();
        $reviewedIds = json_decode($approvedCase->reviewed_document_ids, true) ?? [];
        $this->assertCount(4, $reviewedIds);
        $this->assertDatabaseHas('case_documents', ['case_id' => $approvedCase->id, 'id' => $reviewedIds[0]]);
        $this->assertDatabaseHas('cases', ['status' => 'OPEN', 'consent_text_version' => 'case-intake-v1']);
        $this->assertSame(0, DB::table('referrals')->whereNull('category_id')->count());
        $this->assertGreaterThan(0, DB::table('referrals')->distinct('category_id')->count('category_id'));
        $this->assertSame(
            DB::table('cases')->where('status', 'OPEN')->value('proposed_assessments'),
            DB::table('cases')->where('status', 'OPEN')->value('confirmed_assessments'),
        );
        $this->assertSame(850 * 4 - 128, DB::table('case_documents')->count());
        $this->assertSame(
            DB::table('case_documents')->count(),
            DB::table('audit_logs')->where('module', 'case_document')->count(),
        );
        $this->assertSame(0, DB::table('cases')->where('status', 'PENDING_REVIEW')->whereNotNull('reviewed_document_ids')->where('reviewed_document_ids', '!=', '[]')->count());

        $caseCount = DB::table('cases')->count();
        $this->seed(TestingSeeder::class);

        $this->assertDatabaseCount('cases', $caseCount);
        $this->assertFalse(Schema::hasTable('clients'));
        $this->assertSame('OWB-202610-00853', $generator->nextCaseNumber());
    }
}
