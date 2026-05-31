<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class SyncPhilippineAddressesJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly bool $force = false,
    ) {}

    public function handle(): void
    {
        $exitCode = Artisan::call('philippine-addresses:sync', [
            '--force' => $this->force,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Address sync failed with exit code: '.$exitCode);
        }
    }
}
