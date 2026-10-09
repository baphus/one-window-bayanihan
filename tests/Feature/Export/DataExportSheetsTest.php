<?php

namespace Tests\Feature\Export;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\CaseFile;
use App\Models\Client;
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
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $client = Client::factory()->create();
        CaseFile::factory()->create(['client_id' => $client->id, 'user_id' => $admin->id]);

        $sheets = (new DataExportQueries)->fullExportSheets($admin);

        $titles = array_column($sheets, 'title');
        $expected = array_map(ucfirst(...), ColumnMaps::getAllTables());

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
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $client = Client::factory()->create();
        $case = CaseFile::factory()->create(['client_id' => $client->id, 'user_id' => $admin->id]);

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
        $this->assertTrue($queries->getClients($admin)->contains('id', $client->id), 'getClients() delegation lost the client');
    }

    /** An agency user is scoped out of reference data and their own sheets. */
    #[Test]
    public function agency_user_is_scoped_out_of_users_reference_sheet(): void
    {
        $agency = User::factory()->create(['role' => 'AGENCY']);

        $this->assertCount(0, (new DataExportQueries)->getUsers($agency));
    }
}
