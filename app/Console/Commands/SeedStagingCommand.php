<?php

namespace App\Console\Commands;

use Database\Seeders\StagingDatabaseSeeder;
use Illuminate\Console\Command;

class SeedStagingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:staging {--fresh : Wipe and reseed the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed staging environment with comprehensive test data (1000+ cases)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fresh = $this->option('fresh');

        if ($fresh) {
            if (!$this->confirm('This will wipe the entire database. Continue?')) {
                $this->line('Aborted.');
                return 1;
            }

            $this->call('migrate:fresh', ['--force' => true]);
            $this->line('');
        }

        $this->call('db:seed', [
            '--class' => StagingDatabaseSeeder::class,
            '--force' => true,
        ]);

        $this->info('✓ Staging database ready for testers!');
        $this->info('');
        $this->info('Test Credentials:');
        $this->line('  Admin:         admin@test.gov.ph / 123456');
        $this->line('  Case Manager:  casemanager1@test.gov.ph / 123456');
        $this->line('  Agency:        agency1@test.gov.ph / 123456');

        return 0;
    }
}
