<?php

namespace App\Console\Commands;

use App\Services\AuditCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAuditCategories extends Command
{
    protected $signature = 'audit:backfill-categories {--dry-run : Report counts without updating}';

    protected $description = 'Assign a category to audit log entries created before category stamping existed';

    public function handle(): int
    {
        // Distinct (module, action, user attribution) triples are few; one
        // UPDATE per triple instead of row-by-row writes.
        $triples = DB::table('audit_logs')
            ->whereNull('category')
            ->selectRaw('module, action, (user_id IS NULL) as unattributed')
            ->distinct()
            ->get();

        if ($triples->isEmpty()) {
            $this->info('No audit log entries missing a category.');

            return Command::SUCCESS;
        }

        $updated = 0;

        DB::transaction(function () use ($triples, &$updated) {
            DB::statement("SET LOCAL app.allow_audit_mutations = 'true'");

            foreach ($triples as $triple) {
                $category = AuditCategory::forBackfill(
                    (string) $triple->module,
                    (string) $triple->action,
                    $triple->unattributed ? null : 'attributed'
                );

                if ($this->option('dry-run')) {
                    $count = DB::table('audit_logs')
                        ->whereNull('category')
                        ->where('module', $triple->module)
                        ->where('action', $triple->action)
                        ->when($triple->unattributed, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->whereNotNull('user_id'))
                        ->count();
                    $this->line("[DRY RUN] {$triple->module}/{$triple->action} (".($triple->unattributed ? 'system actor' : 'user').") → {$category}: {$count} rows");

                    continue;
                }

                $updated += DB::table('audit_logs')
                    ->whereNull('category')
                    ->where('module', $triple->module)
                    ->where('action', $triple->action)
                    ->when($triple->unattributed, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->whereNotNull('user_id'))
                    ->update(['category' => $category]);
            }
        });

        if (! $this->option('dry-run')) {
            $this->info("Backfilled category on {$updated} audit log entries.");
        }

        return Command::SUCCESS;
    }
}
