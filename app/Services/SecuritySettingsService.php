<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\SystemSetting;

class SecuritySettingsService
{
    public function getSettings(): array
    {
        return [
            'two_factor_required' => SystemSetting::getValue('two_factor_required', false),
        ];
    }

    public function update(array $data): void
    {
        $settings = [
            'two_factor_required' => 'bool',
        ];

        $changedKeys = [];

        foreach ($settings as $key => $type) {
            if (array_key_exists($key, $data)) {
                SystemSetting::setValue($key, $data[$key], 'security', "Security setting: $key");
                $changedKeys[] = $key;
            }
        }

        if ($changedKeys !== []) {
            SecurityAuditLogger::log(
                'security_settings',
                'Security settings updated: '.implode(', ', $changedKeys),
                null,
                AuditAction::UPDATE->value,
            );
        }
    }
}
