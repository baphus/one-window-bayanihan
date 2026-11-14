<?php

namespace Tests\Feature;

use App\DTOs\FileStoreResult;
use App\Enums\UserRole;
use App\Exceptions\SafeException;
use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\User;
use App\Services\Dashboard\DashboardQueries;
use App\Services\DashboardService;
use App\Services\Export\Queries\ReferralQueries;
use App\Services\ReferralService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReferralCategorySnapshotBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_sort_use_snapshots_without_duplicating_referrals(): void
    {
        $agency = Agency::factory()->create();
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $alpha = CaseCategory::factory()->create(['name' => 'Alpha']);
        $beta = CaseCategory::factory()->create(['name' => 'Beta']);
        $zulu = CaseCategory::factory()->create(['name' => 'Zulu']);
        $snapshot = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $zulu->id, 'status' => 'PENDING']);
        $snapshot->categories()->attach([$alpha->id, $beta->id]);
        $legacy = Referral::factory()->create(['case_id' => $case->id, 'agcy_id' => $agency->id, 'category_id' => $zulu->id, 'status' => 'COMPLETED']);

        $aggregate = app(DashboardQueries::class)->buildRecentCasesReferralAggregates([$case->id])[$case->id];
        $this->assertSame(2, $aggregate['referral_count']);
        $this->assertSame(1, $aggregate['active_referral_count']);
        $this->assertSame('Alpha, Beta, Zulu', $aggregate['category']);
        $mix = collect(app(DashboardService::class)->getAgencyPeriodData($agency->id)['category_mix'])->keyBy('label');
        foreach (['Alpha', 'Beta', 'Zulu'] as $name) {
            $this->assertSame(1, $mix[$name]['total']);
            $this->assertSame(1, $mix[$name]['cases_reached']);
        }
        $sorted = app(ReferralService::class)->getReferrals(['sort' => 'category', 'direction' => 'asc', 'case_id' => $case->id]);
        $this->assertSame([$snapshot->id, $legacy->id], $sorted->pluck('id')->all());
    }

    public function test_invalid_agency_and_missing_override_roll_back(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $category = CaseCategory::factory()->create();
        $case->categories()->attach($category);
        $agency = Agency::factory()->create(['is_active' => true]);
        $category->suggestedAgencies()->attach($agency);
        $this->actingAsWithMfa($manager);
        $payload = ['case_id' => $case->id, 'agcy_ids' => [$agency->id, fake()->uuid()], 'concern' => 'Help'];
        $this->post(route('referrals.store'), $payload)->assertSessionHasErrors('agcy_ids.1');
        $other = Agency::factory()->create(['is_active' => true]);
        $this->post(route('referrals.store'), [...$payload, 'agcy_ids' => [$agency->id, $other->id]])->assertSessionHasErrors('override_reason');
        $this->assertSame(0, Referral::where('case_id', $case->id)->count());
    }

    public function test_bulk_upload_failure_cleans_all_paths_and_sends_no_notifications(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $case->categories()->attach(CaseCategory::factory()->create());
        $agencies = Agency::factory()->count(2)->create(['is_active' => true]);
        $storage = \Mockery::mock(StorageService::class);
        $result = fn ($path) => new FileStoreResult(path: $path, originalName: 'a.pdf', storedName: 'a.pdf', type: 'application/pdf', size: 1);
        $transactionLevel = DB::transactionLevel();
        $storage->shouldReceive('store')->times(4)->andReturnUsing(function () use (&$calls, $result, $transactionLevel) {
            $this->assertSame($transactionLevel, DB::transactionLevel());

            return match ($calls = ($calls ?? 0) + 1) {
                1 => $result('first'), 2 => $result('second'), 3 => $result('third'),
                default => new FileStoreResult(path: '', originalName: 'a.pdf', storedName: '', type: 'application/pdf', size: 0, success: false, error: 'outage'),
            };
        });
        foreach (['first', 'second', 'third'] as $path) {
            $storage->shouldReceive('delete')->once()->with($path)->andReturn(true);
        }
        try {
            app(ReferralService::class)->createReferralsWithDocuments(['case_id' => $case->id, 'agcy_ids' => $agencies->modelKeys(), 'concern' => 'Help', 'override_reason' => 'Specialist'], $manager->id, [UploadedFile::fake()->create('a.pdf'), UploadedFile::fake()->create('b.pdf')], $storage);
            $this->fail('Upload failure expected.');
        } catch (SafeException $exception) {
            $this->assertSame('outage', $exception->userMessage);
        }
        $this->assertSame(0, Referral::where('case_id', $case->id)->count());
        Notification::assertNothingSent();
    }

    public function test_bulk_snapshots_categories_and_rolls_back_on_duplicate(): void
    {
        $manager = User::factory()->create(['role' => UserRole::CASE_MANAGER->value]);
        $case = CaseFile::factory()->create(['status' => 'OPEN']);
        $categories = CaseCategory::factory()->count(2)->create();
        $case->categories()->sync($categories->modelKeys());
        $agencies = Agency::factory()->count(2)->create(['is_active' => true]);
        foreach ($categories as $category) {
            $category->suggestedAgencies()->sync($agencies->modelKeys());
        }
        $this->actingAsWithMfa($manager);
        $payload = ['case_id' => $case->id, 'agcy_ids' => $agencies->modelKeys(), 'concern' => 'Help'];
        $this->post(route('referrals.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, Referral::where('case_id', $case->id)->count());
        $case->categories()->sync([$categories[0]->id]);
        $this->assertCount(2, app(ReferralService::class)->getReferrals(['category_ids' => [$categories[1]->id]])->items());
        $this->assertCount(2, app(ReferralQueries::class)->getReferralsExport($manager, ['category_ids' => $categories->modelKeys()]));
        $third = Agency::factory()->create(['is_active' => true]);
        $categories[0]->suggestedAgencies()->attach($third);
        $this->post(route('referrals.store'), [...$payload, 'agcy_ids' => [$third->id, $agencies[0]->id]])->assertSessionHasErrors('concern');
        $this->assertSame(2, Referral::where('case_id', $case->id)->count());
    }
}
