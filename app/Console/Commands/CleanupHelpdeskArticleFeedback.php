<?php

namespace App\Console\Commands;

use App\Models\HelpdeskArticleFeedback;
use App\Settings\SettingRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('app:cleanup-helpdesk-article-feedback')]
#[Description('Delete help article feedback older than the configured retention period')]
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

    /**
     * Was hard-coded to 12 months here and again in HelpdeskFeedbackService.
     * Both now read the same setting; this is the writer, that is the reader.
     */
    private static function retentionCutoff(): Carbon
    {
        $days = SettingRegistry::int('helpdesk_feedback_retention_days');

        return now()->subDays($days);
    }
}
