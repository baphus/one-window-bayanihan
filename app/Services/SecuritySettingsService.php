<?php

namespace App\Services;

use App\Models\SystemSetting;

class SecuritySettingsService
{
    public function getSettings(): array
    {
        return [
            'password_min_length' => SystemSetting::getValue('password_min_length', 8),
            'password_require_special' => SystemSetting::getValue('password_require_special', true),
            'password_require_numbers' => SystemSetting::getValue('password_require_numbers', true),
            'password_expiry_days' => SystemSetting::getValue('password_expiry_days', 90),
            'session_lifetime_minutes' => SystemSetting::getValue('session_lifetime_minutes', 120),
            'max_login_attempts' => SystemSetting::getValue('max_login_attempts', 5),
            'lockout_duration_minutes' => SystemSetting::getValue('lockout_duration_minutes', 15),
            'ip_whitelist_enabled' => SystemSetting::getValue('ip_whitelist_enabled', false),
            'ip_whitelist_ips' => SystemSetting::getValue('ip_whitelist_ips', ''),
            'two_factor_required' => SystemSetting::getValue('two_factor_required', false),
        ];
    }

    public function update(array $data): void
    {
        $settings = [
            'password_min_length' => 'int',
            'password_require_special' => 'bool',
            'password_require_numbers' => 'bool',
            'password_expiry_days' => 'int',
            'session_lifetime_minutes' => 'int',
            'max_login_attempts' => 'int',
            'lockout_duration_minutes' => 'int',
            'ip_whitelist_enabled' => 'bool',
            'ip_whitelist_ips' => 'string',
            'two_factor_required' => 'bool',
        ];

        foreach ($settings as $key => $type) {
            if (array_key_exists($key, $data)) {
                SystemSetting::setValue($key, $data[$key], 'security', "Security setting: $key");
            }
        }
    }
}
