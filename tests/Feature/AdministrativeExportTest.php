<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseEvent;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\Export\DataExportService;
use App\Services\Reports\AdministrativeExportService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdministrativeExportTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'bayanihan_test') {
            throw new \RuntimeException('Administrative export tests require pgsql/bayanihan_test.');
        }
    }

    private function params(): array
    {
        return ['from' => '2036-01-01', 'to' => '2036-12-31'];
    }

    #[Test]
    public function only_authenticated_admin_with_mfa_may_request_export(): void
    {
        $this->get(route('admin.exports.administrative', $this->params()))->assertRedirect(route('login'));
        foreach ([UserRole::CASE_MANAGER, UserRole::AGENCY, UserRole::OFW] as $role) {
            $user = User::factory()->create(['role' => $role->value]);
            $this->actingAsWithMfa($user)->get(route('admin.exports.administrative', $this->params()))->assertForbidden();
        }
    }

    #[Test]
    public function rejects_invalid_dates_and_deleted_agency_and_audits_safe_rejection(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAsWithMfa($admin)->get(route('admin.exports.administrative', ['from' => 'not-date', 'to' => '2036-01-01']))->assertUnprocessable();
        $entry = AuditLog::query()->where('action', 'EXPORT')->whereJsonContains('new_value->outcome', 'REJECTED')->firstOrFail();
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertStringNotContainsString('not-date', json_encode($entry->new_value));
        $this->get(route('admin.exports.administrative', ['from' => '2036-01-02', 'to' => '2036-01-01']))->assertUnprocessable();
        $agency = Agency::factory()->create(['is_deleted' => true]);
        $this->get(route('admin.exports.administrative', $this->params() + ['agency_id' => $agency->id]))->assertUnprocessable();
        $this->get(route('admin.exports.administrative', $this->params() + ['province' => 'foo']))->assertUnprocessable();
        $this->get(route('admin.exports.administrative'))->assertUnprocessable();
    }

    #[Test]
    public function generates_five_sheet_download_with_case_and_referral_counts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = CaseFile::factory()->create(['created_at' => '2036-06-01 10:00:00', 'status' => 'CLOSED']);
        $referral = Referral::factory()->create(['case_id' => $case->id, 'created_at' => '2037-01-01 10:00:00']);
        CaseEvent::create(['case_id' => $case->id, 'referral_id' => $referral->id, 'type' => CaseEvent::TYPE_REFERRAL_SENT, 'title' => 'Sent', 'actor_type' => 'case_manager', 'occurred_at' => '2037-01-01 10:00:00']);
        $response = $this->actingAsWithMfa($admin)->get(route('admin.exports.administrative', $this->params()));
        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
        $this->assertStringContainsString('administrative-export-', $response->headers->get('content-disposition'));
        $this->assertTrue(AuditLog::query()->where('action', 'EXPORT')->whereJsonContains('new_value->outcome', 'GENERATED')->exists());
    }

    #[Test]
    public function rejects_over_cap_without_workbook(): void
    {
        config(['reports.export_row_cap' => 1]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $case = CaseFile::factory()->create(['created_at' => '2036-06-01 10:00:00']);
        Referral::factory()->count(2)->create(['case_id' => $case->id]);
        $this->actingAsWithMfa($admin)->get(route('admin.exports.administrative', $this->params()))
            ->assertUnprocessable()->assertJsonPath('message', 'Export exceeds row limit. Narrow dates or agency.')
            ->assertJsonPath('counts.referrals', 2);
    }

    #[Test]
    public function agency_scope_includes_only_live_referrals_and_counts_cases_once(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $case = CaseFile::factory()->closed()->create(['created_at' => '2036-03-01 10:00:00']);
        $category = CaseCategory::factory()->create();
        $case->categories()->attach($category->id);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyA->id, 'created_at' => '2037-01-01 10:00:00']);
        Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyB->id, 'created_at' => '2037-02-01 10:00:00']);
        $removed = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agencyA->id]);
        $removed->delete();
        $response = $this->actingAsWithMfa($admin)->get(route('admin.exports.administrative', $this->params() + ['agency_id' => $agencyA->id]));
        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertSame('PK', substr($content, 0, 2));
        $this->assertStringNotContainsString((string) $agencyB->name, $content);
        $path = tempnam(sys_get_temp_dir(), 'agency-scope-');
        file_put_contents($path, $content);
        try {
            $book = IOFactory::load($path);
            $this->assertSame(2, $book->getSheetByName('Cases')->getHighestDataRow());
            $this->assertSame(2, $book->getSheetByName('Referrals')->getHighestDataRow());
            $this->assertSame(1, $book->getSheetByName('Cases')->getCell('E2')->getValue());
            $this->assertSame($agencyA->name, $book->getSheetByName('Referrals')->getCell('C2')->getValue());
            $book->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function includes_only_local_date_window_and_excludes_deleted_records(): void
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $start = CaseFile::factory()->create(['created_at' => '2036-01-01 08:00:00+00', 'case_number' => 'OWB-IN-START']);
        $end = CaseFile::factory()->create(['created_at' => '2036-02-01 07:59:59+00', 'case_number' => 'OWB-IN-END']);
        $outside = CaseFile::factory()->create(['created_at' => '2036-02-01 08:00:00+00', 'case_number' => 'OWB-OUTSIDE']);
        $deleted = CaseFile::factory()->create(['created_at' => '2036-01-15 12:00:00+00', 'case_number' => 'OWB-DELETED']);
        $deleted->delete();
        $response = $this->actingAsWithMfa($admin)->get(route('admin.exports.administrative', ['from' => '2036-01-01', 'to' => '2036-01-31']));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'admin-test-');
        file_put_contents($path, $response->streamedContent());
        $zip = new \ZipArchive;
        $this->assertSame(true, $zip->open($path));
        $cells = $zip->getFromName('xl/sharedStrings.xml');
        $this->assertStringContainsString('OWB-IN-START', $cells);
        $this->assertStringContainsString('OWB-IN-END', $cells);
        $this->assertStringNotContainsString('OWB-OUTSIDE', $cells);
        $this->assertStringNotContainsString('OWB-DELETED', $cells);
        $zip->close();
        @unlink($path);
        $this->assertSame(3, CaseFile::query()->where('is_deleted', false)->whereBetween('created_at', ['2036-01-01 08:00:00', '2036-02-01 08:00:00'])->count());
    }

    #[Test]
    public function admin_without_mfa_session_is_denied_and_export_is_throttled(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAs($admin)->get(route('admin.exports.administrative', $this->params()))->assertRedirect();
        $this->actingAsWithMfa($admin);
        $key = 'administrative-test-'.Str::uuid();
        RateLimiter::for('reports-export', fn () => Limit::perMinute(1)->by($key));
        $this->getJson(route('admin.exports.administrative', $this->params()))->assertNotFound();
        $this->getJson(route('admin.exports.administrative', $this->params()))->assertStatus(429);
    }

    #[Test]
    public function generated_audit_failure_removes_temporary_workbook_and_fails_closed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $path = tempnam(sys_get_temp_dir(), 'admin-test-');
        file_put_contents($path, 'private workbook');
        $this->mock(AdministrativeExportService::class, function ($mock) use ($path) {
            $mock->shouldReceive('generate')->once()->andReturn([
                'path' => $path, 'filename' => 'test.xlsx', 'counts' => ['cases' => 1, 'referrals' => 0],
            ]);
        });
        AuditLog::creating(function ($log) {
            if (($log->new_value['outcome'] ?? null) === 'GENERATED') {
                throw new \RuntimeException('Audit unavailable');
            }
        });
        try {
            $this->actingAsWithMfa($admin)->getJson(route('admin.exports.administrative', $this->params()))->assertStatus(500);
            $this->assertFileDoesNotExist($path);
            $this->assertFalse(AuditLog::query()->whereJsonContains('new_value->outcome', 'GENERATED')->exists());
            $this->assertTrue(AuditLog::query()->whereJsonContains('new_value->outcome', 'FAILED')->exists());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function exact_cap_exports_every_row_including_cases_without_referrals(): void
    {
        config(['reports.export_row_cap' => 2]);
        CaseFile::factory()->count(2)->create(['created_at' => '2036-06-01 10:00:00']);
        $result = app(AdministrativeExportService::class)->generate($this->params());
        try {
            $book = IOFactory::load($result['path']);
            $this->assertSame(3, $book->getSheetByName('Cases')->getHighestDataRow());
            $this->assertSame(1, $book->getSheetByName('Referrals')->getHighestDataRow());
            $this->assertSame(0, $book->getSheetByName('Cases')->getCell('E2')->getValue());
        } finally {
            if (isset($book)) {
                $book->disconnectWorksheets();
            }
            @unlink($result['path']);
        }
    }

    #[Test]
    public function snapshot_keeps_original_status_when_another_connection_updates_case(): void
    {
        $case = CaseFile::factory()->create(['created_at' => '2036-06-01 10:00:00', 'status' => 'OPEN']);
        config(['database.connections.export_concurrent' => config('database.connections.pgsql')]);
        $other = DB::connection('export_concurrent');
        $changed = false;
        DB::listen(function ($query) use ($case, $other, &$changed) {
            if (! $changed && str_contains($query->sql, 'count(*)') && str_contains($query->sql, '"cases"')) {
                $changed = true;
                $other->table('cases')->where('id', $case->id)->update(['status' => 'CLOSED']);
            }
        });
        try {
            $result = app(AdministrativeExportService::class)->generate($this->params());
            $book = IOFactory::load($result['path']);
            $this->assertTrue($changed);
            $this->assertSame('OPEN', $book->getSheetByName('Cases')->getCell('C2')->getValue());
            $summary = $book->getSheetByName('Management Summary')->toArray();
            $this->assertSame('OPEN', $summary[1][1]);
            $this->assertSame('CLOSED', $other->table('cases')->where('id', $case->id)->value('status'));
        } finally {
            if (isset($book)) {
                $book->disconnectWorksheets();
            }
            if (isset($result['path'])) {
                @unlink($result['path']);
            }
            DB::disconnect('export_concurrent');
        }
    }

    #[Test]
    public function writer_failure_returns_safe_error_and_correlated_audit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        CaseFile::factory()->create(['created_at' => '2036-06-01 10:00:00']);
        $this->mock(DataExportService::class, function ($mock) {
            $mock->shouldReceive('generateMultiSheetFile')->once()->andThrow(new \RuntimeException('PRIVATE-SENTINEL'));
        });
        $response = $this->actingAsWithMfa($admin)->getJson(route('admin.exports.administrative', $this->params()));
        $response->assertStatus(500);
        $this->assertStringNotContainsString('PRIVATE-SENTINEL', $response->getContent());
        $events = AuditLog::query()->where('action', 'EXPORT')->get()->pluck('new_value');
        $this->assertSame(['REQUESTED', 'FAILED'], $events->pluck('outcome')->all());
        $this->assertCount(1, $events->pluck('correlation_id')->unique());
        $this->assertNotEmpty($events->first()['correlation_id']);
    }

    #[Test]
    public function case_cap_rejects_before_writer_and_limit_boundary_includes_all_rows(): void
    {
        config(['reports.export_row_cap' => 1]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        CaseFile::factory()->count(2)->create(['created_at' => '2036-06-01 10:00:00']);
        $this->mock(DataExportService::class)->shouldNotReceive('generateMultiSheetFile');
        $this->actingAsWithMfa($admin)->getJson(route('admin.exports.administrative', $this->params()))
            ->assertUnprocessable()->assertJsonPath('counts.cases', 2);
    }

    #[Test]
    public function rejects_empty_scope_without_workbook(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $this->actingAsWithMfa($admin)->get(route('admin.exports.administrative', $this->params()))->assertNotFound()->assertJsonPath('message', 'No cases match selected scope.');
    }
}
