<?php

namespace App\Console\Commands;

use App\Helpers\CacheHelper;
use App\Http\Controllers\StakeholderController;
use App\Models\Agency;
use App\Services\DashboardService;
use App\Services\ReferenceDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WarmCache extends Command
{
    protected $signature = 'cache:warm {--force : Clear existing caches before warming}';

    protected $description = 'Pre-warm critical application caches (dashboard, reference data, stakeholder)';

    public function handle(): int
    {
        $this->components->info('Warming application caches...');

        if ($this->option('force')) {
            $this->components->warn('Force mode: clearing existing caches first');
            $this->call('cache:clear');
        }

        // 1. Reference data (agencies, categories, issues, users)
        $this->components->task('Reference data', function () {
            $ref = app(ReferenceDataService::class);
            $ref->getAgenciesDropdown();
            $ref->getActiveCategories();
            $ref->getActiveIssues();
            $ref->getCaseManagerUsers();
            $ref->getAgenciesWithServices();
            $ref->getDefaultAgency();
            $ref->getActiveAgenciesFull();
        });

        // 2. Dashboard counts (the most-hit cached queries)
        $this->components->task('Dashboard counts', function () {
            $dashboard = app(DashboardService::class);
            // Trigger the cached count queries by calling getAdminData
            // (which populates cm_counts, admin_counts, user counts, agency counts)
            $dashboard->getAdminData();
        });

        // 3. Stakeholder agencies list
        $this->components->task('Stakeholder data', function () {
            CacheHelper::safeRemember('stakeholder:agencies_list', 600, function () {
                return Agency::with(['services'])
                    ->withCount([
                        'referrals as total_referrals_count',
                        'referrals as active_referrals_count' => fn ($q) => $q->whereIn('status', ['PENDING', 'PROCESSING', 'FOR_COMPLIANCE']),
                        'referrals as completed_referrals_count' => fn ($q) => $q->where('status', 'COMPLETED'),
                    ])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->toArray();
            });
        });

        $this->newLine();
        $this->components->info('Cache warming complete.');

        return self::SUCCESS;
    }
}
