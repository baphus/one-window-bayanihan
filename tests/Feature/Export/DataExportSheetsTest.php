<?php

namespace Tests\Feature\Export;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\User;
use App\Services\Export\ColumnMaps;
use App\Services\Export\DataExportQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataExportSheetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    /**
     * The orchestrator delegates every sheet to its own query builder. A dropped
     * or mis-wired delegation fails silently — ColumnMaps still lists the table,
     * so the workbook would just carry an empty sheet.
     */
    #[Test]
    public function full_export_sheets_covers_every_mapped_table_in_order(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        CaseFile::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'OPEN']);

        $sheets = (new DataExportQueries)->fullExportSheets($admin);

        $titles = array_column($sheets, 'title');
        $expected = array_map(fn ($table) => match ($table) {
            'clients' => 'Case intake records', 'client_addresses' => 'Case addresses',
            'client_employments' => 'Case employment', 'next_of_kin' => 'Case representatives',
            default => ucfirst($table),
        }, ColumnMaps::getAllTables());

        $this->assertSame($expected, $titles);

        foreach ($sheets as $sheet) {
            $this->assertNotEmpty($sheet['columnMap'], $sheet['title'].' lost its column map');
            $this->assertInstanceOf(Collection::class, $sheet['rows'], $sheet['title'].' lost its rows');
        }
    }

    /**
     * Ten of the thirteen sheet queries have no other caller — only
     * fullExportSheets() reaches them. Each must still resolve to a working
     * builder rather than a renamed or deleted method.
     */
    #[Test]
    public function every_delegated_sheet_query_returns_a_collection(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = CaseFile::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'OPEN']);

        $queries = new DataExportQueries;

        $scoped = [
            'getUsers' => $queries->getUsers($admin),
            'getCases' => $queries->getCases($admin),
            'getClients' => $queries->getClients($admin),
            'getReferrals' => $queries->getReferrals($admin),
            'getMilestones' => $queries->getMilestones($admin),
            'getNextOfKins' => $queries->getNextOfKins($admin),
            'getCaseDocuments' => $queries->getCaseDocuments($admin),
            'getClientAddresses' => $queries->getClientAddresses($admin),
            'getClientEmployments' => $queries->getClientEmployments($admin),
        ];

        $reference = [
            'getAgencies' => $queries->getAgencies(),
            'getServices' => $queries->getServices(),
            'getCaseCategories' => $queries->getCaseCategories(),
            'getCaseStatuses' => $queries->getCaseStatuses(),
        ];

        foreach ([...$scoped, ...$reference] as $method => $result) {
            $this->assertInstanceOf(Collection::class, $result, $method.'() is not wired to a query builder');
        }

        // Presence, not count: a full-suite neighbour can leave users behind, and
        // an exact count would fail for reasons the delegation has nothing to do with.
        $this->assertTrue($queries->getUsers($admin)->contains('id', $admin->id), 'getUsers() delegation lost the acting user');
        $this->assertTrue($queries->getCases($admin)->contains('id', $case->id), 'getCases() delegation lost the case');
        $this->assertTrue($queries->getClients($admin)->contains('id', $case->id), 'getClients() delegation lost the case intake record');
    }

    /** An agency user is scoped out of reference data and their own sheets. */
    #[Test]
    public function agency_user_is_scoped_out_of_users_reference_sheet(): void
    {
        $agency = User::factory()->create(['role' => UserRole::AGENCY->value]);

        $this->assertCount(0, (new DataExportQueries)->getUsers($agency));
    }

    #[Test]
    public function independent_case_snapshots_export_decrypted_fields_and_unsplit_representative_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $owner = User::factory()->create(['role' => UserRole::OFW->value]);
        foreach (['Japan', 'Canada'] as $country) {
            CaseFile::factory()->create([
                'user_id' => $admin->id, 'ofw_user_id' => $owner->id, 'status' => 'OPEN',
                'date_of_birth' => '1990-06-15', 'intake_date' => '2026-06-14',
                'employment_country' => $country, 'address_street' => 'Private street',
                'nok_name' => 'Single unsplit representative', 'nok_contact_number' => '09123456789',
            ]);
        }
        $queries = new DataExportQueries;
        $rows = $queries->getClientsExport($admin);
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['Japan', 'Canada'], $rows->pluck('employment_country')->all());
        $this->assertSame([35, 35], $rows->pluck('age')->all());
        $this->assertStringContainsString('Private street', $rows->first()->full_address);
        $this->assertSame('Single unsplit representative', $queries->getNextOfKins($admin)->first()->name);
        $this->assertSame('09123456789', $queries->getNextOfKins($admin)->first()->phone_number);
    }
}
