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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestingSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('object-storage');
    }

    public function test_seeded_cases_share_the_counter_with_existing_and_future_cases(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 04:00:00', 'UTC'));
        $generator = app(CaseNumberGenerator::class);
        $existing = CaseFile::factory()->withIntake()->create([
            'case_number' => $generator->nextCaseNumber(),
            'intake_date' => '2026-10-15',
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
        $document = DB::table('case_documents')->where('id', $reviewedIds[0])->first();
        Storage::disk('object-storage')->assertExists($document->file_path);
        $this->assertSame($document->size, strlen(Storage::disk('object-storage')->get($document->file_path)));
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('object-storage')->get($document->file_path));
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

    public function test_failed_document_write_rolls_back_synthetic_cases(): void
    {
        $disk = Storage::disk('object-storage');
        Storage::shouldReceive('disk')->with('object-storage')->andReturn(new class($disk)
        {
            public function __construct(private $disk) {}

            public function put(string $path, string $contents): bool
            {
                return false;
            }

            public function delete(string $path): bool
            {
                return $this->disk->delete($path);
            }
        });

        try {
            $this->seed(TestingSeeder::class);
            $this->fail('Seeder must fail when document write fails.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Failed storing synthetic document', $exception->getMessage());
        }

        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('case_documents', 0);
    }

    public function test_unrelated_cases_are_not_rewritten_or_added_to_seeded_audit_timeline(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 04:00:00', 'UTC'));
        $existing = CaseFile::factory()->withIntake()->create(['summary' => 'Real existing case']);
        $creator = User::factory()->create();
        DB::table('cases')->where('id', $existing->id)->update(['user_id' => $creator->id]);
        for ($i = 0; $i < 11; $i++) {
            CaseFile::factory()->withIntake()->create();
        }
        $before = DB::table('cases')->where('id', $existing->id)->first();
        $existingAuditCount = DB::table('audit_logs')->where('module', 'case')->where('entity_id', $existing->id)->count();
        $createdAuditCount = DB::table('audit_logs')->where('module', 'case')->where('action', 'CREATE')->count();

        $this->seed(TestingSeeder::class);

        $this->assertDatabaseCount('cases', 862);
        $this->assertEquals($before, DB::table('cases')->where('id', $existing->id)->first());
        $this->assertSame($existingAuditCount, DB::table('audit_logs')->where('module', 'case')->where('entity_id', $existing->id)->count());
        $this->assertSame($createdAuditCount + 850, DB::table('audit_logs')->where('module', 'case')->where('action', 'CREATE')->count());

        $auditCount = DB::table('audit_logs')->count();
        $this->seed(TestingSeeder::class);
        $this->assertDatabaseCount('cases', 862);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }
}
