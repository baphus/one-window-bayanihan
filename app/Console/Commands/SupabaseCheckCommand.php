<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SupabaseCheckCommand extends Command
{
    protected $signature = 'supabase:check';

    protected $description = 'Verify Supabase configuration and connectivity';

    /**
     * Config keys to check with their display names and source config path.
     */
    private array $configs = [
        ['key' => 'services.supabase.url', 'label' => 'SUPABASE_URL', 'secret' => false],
        ['key' => 'services.supabase.key', 'label' => 'SUPABASE_KEY', 'secret' => true],
        ['key' => 'services.supabase.service_key', 'label' => 'SUPABASE_SERVICE_KEY', 'secret' => true],
    ];

    public function handle(): int
    {
        $this->components->info('Supabase Configuration Check');
        $this->newLine();

        $allPass = true;

        // Check required config values
        $this->components->twoColumnDetail('<fg=yellow>Config</>', '<fg=yellow>Status</>');
        foreach ($this->configs as $cfg) {
            $value = Config::get($cfg['key']);
            $display = $cfg['secret']
                ? ($value ? substr($value, 0, 8).'...' : '<empty>')
                : ($value ?: '<empty>');

            if ($value) {
                $this->components->twoColumnDetail($cfg['label'], "<fg=green>✓ {$display}</>");
            } else {
                $this->components->twoColumnDetail($cfg['label'], '<fg=red>✗ not set</>');
                $allPass = false;
            }
        }

        // Check SSL mode
        $sslmode = Config::get('database.connections.pgsql.sslmode');
        if ($sslmode === 'require') {
            $this->components->twoColumnDetail('DB_SSLMODE', '<fg=green>✓ require</>');
        } elseif ($sslmode) {
            $this->components->twoColumnDetail('DB_SSLMODE', "<fg=yellow>⚠ {$sslmode} (recommend: require)</>");
        } else {
            $this->components->twoColumnDetail('DB_SSLMODE', '<fg=red>✗ not configured</>');
            $allPass = false;
        }

        $this->newLine();

        // Try connectivity check if URL is configured
        $url = Config::get('services.supabase.url');
        if ($url) {
            $this->components->task('Supabase API connectivity', function () use ($url) {
                try {
                    $response = Http::timeout(3)->head($url.'/rest/v1/');
                    if ($response->successful()) {
                        return true;
                    }
                    // 401/403 is expected without valid key — means reachable
                    if (in_array($response->status(), [401, 403])) {
                        return true;
                    }
                    $this->warn("Unexpected HTTP {$response->status()}");

                    return false;
                } catch (\Exception $e) {
                    $this->warn("Network error: {$e->getMessage()}");

                    return false;
                }
            });
        } else {
            $this->components->twoColumnDetail('API Connectivity', '<fg=yellow>⚠ skipped (URL not set)</>');
        }

        $this->newLine();

        if ($allPass) {
            $this->components->success('All Supabase configuration values are set.');

            return Command::SUCCESS;
        }

        $this->components->warn('Some Supabase configuration values are missing.');
        $this->line('Update your .env file with the values from your Supabase project dashboard.');
        $this->line('See docs/supabase-setup.md for setup instructions.');

        return Command::FAILURE;
    }
}
