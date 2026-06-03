<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshAnalyticsViews extends Command
{
    protected $signature = 'insights:refresh-views';

    protected $description = 'Refresh analytics materialized views';

    public function handle(): int
    {
        $this->info('Refreshing analytics materialized views...');

        $this->components->task('mv_daily_case_summary', function () {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_case_summary');
        });

        $this->components->task('mv_agency_performance', function () {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_agency_performance');
        });

        $this->info('All materialized views refreshed successfully.');

        return Command::SUCCESS;
    }
}
