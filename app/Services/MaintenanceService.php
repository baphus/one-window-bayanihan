<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;

class MaintenanceService
{
    public function getStatus(): array
    {
        $path = storage_path('framework/down');
        $isDown = file_exists($path);
        $secret = null;
        $retry = null;

        if ($isDown) {
            $data = json_decode(file_get_contents($path), true) ?: [];
            $secret = $data['secret'] ?? null;
            $retry = $data['retry'] ?? null;
        }

        return [
            'active' => $isDown,
            'secret' => $secret,
            'retry' => $retry,
            'since' => $isDown ? date('Y-m-d H:i:s', filemtime($path)) : null,
        ];
    }

    public function enable(?string $secret = null, ?int $retryMinutes = null): void
    {
        if ($this->getStatus()['active']) {
            throw new \RuntimeException('Maintenance mode is already enabled.');
        }

        $params = [];

        if ($secret) {
            $params['--secret'] = $secret;
        }

        if ($retryMinutes) {
            $params['--retry'] = $retryMinutes;
        }

        Artisan::call('down', $params);
    }

    public function disable(): void
    {
        if (! $this->getStatus()['active']) {
            throw new \RuntimeException('Maintenance mode is not enabled.');
        }

        Artisan::call('up');
    }
}
