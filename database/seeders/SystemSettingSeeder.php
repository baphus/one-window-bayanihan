<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Settings\SettingRegistry;
use Illuminate\Database\Seeder;

/**
 * Seeds one row per declared setting, so the page shows the environment-backed
 * default rather than an empty field.
 *
 * firstOrCreate throughout: re-running never overwrites a value an admin has
 * changed.
 */
class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingRegistry::all() as $key => $setting) {
            // env() must not be called outside config/: once `php artisan
            // config:cache` has run, env() returns null everywhere else. The
            // registry's default already mirrors the environment's value, and
            // firstOrCreate below never overwrites an admin's change.
            SystemSetting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => $setting->serialize($setting->default),
                    'category' => $setting->group,
                    'description' => $setting->description,
                ],
            );
        }

        // Removed: nothing reads it, and a toggle that does not change
        // behaviour is worse than no toggle. MFA enforcement is decided by
        // config/mfa.php and is unconditional in production.
        SystemSetting::query()->where('key', 'two_factor_required')->delete();
    }
}
