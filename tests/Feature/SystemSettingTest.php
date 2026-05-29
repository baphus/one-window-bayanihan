<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_category_and_description_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('system_settings', 'category'));
        $this->assertTrue(Schema::hasColumn('system_settings', 'description'));
    }

    public function test_get_by_category_returns_correct_settings(): void
    {
        SystemSetting::setValue('security_flag', 'on', 'security', 'Security flag');
        SystemSetting::setValue('security_mode', 'strict', 'security', 'Security mode');
        SystemSetting::setValue('ui_theme', 'dark', 'ui', 'Theme');

        $settings = SystemSetting::getByCategory('security');

        $this->assertCount(2, $settings);
        $this->assertEqualsCanonicalizing(['security_flag', 'security_mode'], $settings->pluck('key')->all());
    }

    public function test_set_value_with_category_stores_correctly(): void
    {
        SystemSetting::setValue('stored_key', 'stored_value', 'security', 'Stored description');

        $setting = SystemSetting::query()->findOrFail('stored_key');

        $this->assertSame('stored_value', $setting->value);
        $this->assertSame('security', $setting->category);
        $this->assertSame('Stored description', $setting->description);
    }

    public function test_set_value_without_category_remains_backward_compatible(): void
    {
        SystemSetting::setValue('legacy_key', 'legacy_value');

        $setting = SystemSetting::query()->findOrFail('legacy_key');

        $this->assertSame('legacy_value', $setting->value);
        $this->assertNull($setting->category);
        $this->assertNull($setting->description);
    }

    public function test_get_value_returns_correct_value(): void
    {
        SystemSetting::setValue('value_key', 'value_123', 'security', 'Value description');

        $this->assertSame('value_123', SystemSetting::getValue('value_key'));
    }
}
