<?php

namespace Tests\Feature\Export;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PopulatedWorkbookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    private function workbook(string $route, array $filters = []): array
    {
        $response = $this->get(route($route, $filters));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'export-').'.xlsx';
        try {
            file_put_contents($path, $response->streamedContent());

            return IOFactory::load($path)->getActiveSheet()->toArray();
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function case_workbook_uses_case_owned_ofw_and_representative_without_extra_pii(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create([
            'client_type' => 'NEXT_OF_KIN', 'first_name' => 'Affected', 'last_name' => 'Worker',
            'nok_name' => 'Representative Person', 'nok_relationship' => 'Spouse',
            'summary' => 'Snapshot summary', 'status' => 'OPEN',
        ]);
        $this->actingAsWithMfa($user);
        [$headers, $values] = $this->workbook('cases.export-excel');
        $row = array_combine($headers, $values);
        $this->assertSame($case->case_number, $row['Case Number']);
        $this->assertSame('Worker, Affected', $row['OFW Full Name']);
        $this->assertSame('Representative Person', $row['NOK Full Name']);
        $this->assertSame('Spouse', $row['NOK Relationship']);
        $this->assertSame('Snapshot summary', $row['Case Summary']);
        foreach (['Issues/Concern', 'OFW Date of Birth', 'NOK Contact No.', 'NOK Email'] as $forbidden) {
            $this->assertNotContains($forbidden, $headers);
        }
    }

    #[Test]
    public function cases_export_filters_archived_and_excludes_pending_review(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $archived = CaseFile::factory()->create(['status' => 'ARCHIVED']);
        CaseFile::factory()->create(['status' => CaseFile::STATUS_PENDING_REVIEW]);
        $this->actingAsWithMfa($user);
        $this->getJson(route('cases.export-count', ['status' => ['ARCHIVED', CaseFile::STATUS_PENDING_REVIEW]]))
            ->assertJsonPath('count', 1);
        $rows = $this->workbook('cases.export-excel', ['status' => ['ARCHIVED', CaseFile::STATUS_PENDING_REVIEW]]);
        $this->assertCount(2, $rows);
        $this->assertSame($archived->case_number, array_combine($rows[0], $rows[1])['Case Number']);
    }

    #[Test]
    public function referral_workbook_scopes_agency_rows_and_columns(): void
    {
        $own = Agency::factory()->create();
        $other = Agency::factory()->create();
        $case = CaseFile::factory()->create(['first_name' => 'Affected', 'last_name' => 'Worker', 'status' => 'ARCHIVED']);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $own->id, 'concern' => 'Own concern']);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $other->id, 'concern' => 'Other concern']);
        $user = User::factory()->create(['role' => UserRole::AGENCY->value, 'agcy_id' => $own->id]);
        $this->actingAsWithMfa($user);
        $this->getJson(route('referrals.export-count'))->assertOk()->assertJsonPath('count', 1);
        $rows = $this->workbook('referrals.export-excel');
        $this->assertCount(2, $rows);
        $row = array_combine($rows[0], $rows[1]);
        $this->assertSame($case->case_number, $row['Case Number']);
        $this->assertSame('Own concern', $row['Referral Concern']);
        $this->assertSame('Worker, Affected', $row['OFW Name']);
        foreach (['Case Summary', 'Vulnerability', 'Jobsite Country', 'Client Email', 'Date of Birth', 'NOK Email'] as $forbidden) {
            $this->assertNotContains($forbidden, $rows[0]);
        }
    }

    #[Test]
    public function referral_search_matches_listing_service_and_middle_name_without_matching_legacy_text(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create(['middle_name' => 'DistinctiveMiddle']);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'required_services' => 'LegacyOnlyText']);
        $service = Service::create(['agcy_id' => $agency->id, 'name' => 'LinkedService']);
        $referral->services()->attach($service->id);
        $this->actingAsWithMfa($user);

        foreach (['LinkedService', 'DistinctiveMiddle', $referral->id] as $search) {
            $this->getJson(route('referrals.export-count', ['search' => $search]))->assertJsonPath('count', 1);
        }
        $this->getJson(route('referrals.export-count', ['search' => 'LegacyOnlyText']))->assertJsonPath('count', 0);
    }

    #[Test]
    public function referral_count_uses_array_filters_service_search_and_dates(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'ARCHIVED']);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'status' => 'PENDING']);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'status' => 'PROCESSING']);
        $this->actingAsWithMfa($user)->getJson(route('referrals.export-count', [
            'status' => ['PENDING', 'PROCESSING'], 'agcy_id' => [$agency->id],
            'search' => substr($case->case_number, 0, 8), 'date_from' => now()->toDateString(),
        ]))->assertJsonPath('count', 2);
    }
}
