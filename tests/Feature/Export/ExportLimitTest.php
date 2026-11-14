<?php

namespace Tests\Feature\Export;

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Agency;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(HandleInertiaRequests::class);
    }

    #[Test]
    public function case_count_is_uncapped_and_oversized_download_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        DB::statement('INSERT INTO cases (id, case_number, tracker_number, status, is_deleted, created_at, updated_at) SELECT gen_random_uuid(), ? || n, ? || n, ?, false, now(), now() FROM generate_series(1, 10000) n', ['bulk-case-', 'bulk-track-', 'OPEN']);
        $this->actingAsWithMfa($user)->getJson(route('cases.export-count'))->assertJsonPath('count', 10001);
        $this->getJson(route('cases.export-excel'))->assertStatus(422)->assertJsonPath('message', 'More than 10,000 cases match. Narrow filters before exporting.');
    }

    #[Test]
    public function referral_count_is_uncapped_and_oversized_download_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create();
        $agency = Agency::factory()->create();
        DB::statement("INSERT INTO referrals (id, case_id, agcy_id, required_services, status, is_deleted, created_at, updated_at) SELECT gen_random_uuid(), ?, ?, 'Assistance', 'PENDING', false, now(), now() FROM generate_series(1, 10001)", [$case->id, $agency->id]);
        $this->actingAsWithMfa($user)->getJson(route('referrals.export-count'))->assertJsonPath('count', 10001);
        $this->getJson(route('referrals.export-excel'))->assertStatus(422)->assertJsonPath('message', 'More than 10,000 referrals match. Narrow filters before exporting.');
    }
}
