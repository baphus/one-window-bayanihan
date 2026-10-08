<?php

namespace App\Services;

use App\Enums\AuditAction;
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

        SecurityAuditLogger::log(
            'maintenance',
            'Maintenance mode was enabled',
            null,
            AuditAction::UPDATE->value,
        );

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

        SecurityAuditLogger::log(
            'maintenance',
            'Maintenance mode was disabled',
            null,
            AuditAction::UPDATE->value,
        );

        Artisan::call('up');
    }
}
