<?php

namespace App\Models;

use App\Helpers\CacheHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'category', 'description'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = CacheHelper::safeRemember('system_settings', 3600, function () {
            return static::pluck('value', 'key')->toArray();
        });

        $value = $settings[$key] ?? $default;

        if ($value === 'true' || $value === 'false') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    public static function getByCategory(string $category): Collection
    {
        return static::query()->where('category', $category)->get();
    }

    public static function setValue(string $key, mixed $value, ?string $category = null, ?string $description = null): void
    {
        static::updateOrCreate(['key' => $key], [
            'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
            'category' => $category,
            'description' => $description,
        ]);

        Cache::forget('system_settings');
    }
}
