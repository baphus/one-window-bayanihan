<?php

namespace App\Services;

use App\Enums\AuditModule;
use Illuminate\Support\Facades\Artisan;

class MaintenanceService
{
    public function getStatus(): array
    {
        return [
            'active' => app()->maintenanceMode()->active(),
            'secret' => null,
            'retry' => null,
            'since' => null,
        ];
    }

    public function enable(string $secret, ?int $retryMinutes = null): void
    {
        if ($this->getStatus()['active']) {
            throw new \RuntimeException('Maintenance mode is already enabled.');
        }

        AuditLogger::toggleMaintenance()
            ->byId(auth()->id())
            ->module(AuditModule::SYSTEM_SETTING)
            ->context(null, ['enabled' => true])
            ->write();

        $params = ['--secret' => $secret];

        if ($retryMinutes) {
            $params['--retry'] = $retryMinutes * 60;
        }

        Artisan::call('down', $params);
    }

    public function disable(): void
    {
        if (! $this->getStatus()['active']) {
            throw new \RuntimeException('Maintenance mode is not enabled.');
        }

        AuditLogger::toggleMaintenance()
            ->byId(auth()->id())
            ->module(AuditModule::SYSTEM_SETTING)
            ->context(null, ['enabled' => false])
            ->write();

        Artisan::call('up');
    }
}
