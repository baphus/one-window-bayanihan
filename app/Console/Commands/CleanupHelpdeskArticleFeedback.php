<?php

namespace App\Console\Commands;

use App\Models\HelpdeskArticleFeedback;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('app:cleanup-helpdesk-article-feedback')]
#[Description('Delete help article feedback older than 12 months')]
class CleanupHelpdeskArticleFeedback extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        HelpdeskArticleFeedback::where('updated_at', '<', self::retentionCutoff())->delete();

        $this->info('Expired help article feedback deleted.');

        return self::SUCCESS;
    }

    private static function retentionCutoff(): Carbon
    {
        return now()->subMonthsNoOverflow(12);
    }
}
