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

    /**
     * Store a setting value, creating the row when it does not exist yet.
     *
     * Category and description are metadata seeded in the database; they are only
     * written when the row is created, or when the caller explicitly passes a
     * replacement. An update that omits them preserves whatever is already stored
     * instead of nulling it out.
     *
     * @param  string  $key  Setting key.
     * @param  mixed  $value  Value to persist; booleans are stored as 'true'/'false'.
     * @param  string|null  $category  Optional category, applied on create or when non-null.
     * @param  string|null  $description  Optional description, applied on create or when non-null.
     */
    public static function setValue(string $key, mixed $value, ?string $category = null, ?string $description = null): void
    {
        $setting = static::query()->firstOrNew(['key' => $key]);

        $setting->value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        if ($category !== null || ! $setting->exists) {
            $setting->category = $category;
        }

        if ($description !== null || ! $setting->exists) {
            $setting->description = $description;
        }

        $setting->save();

        Cache::forget('system_settings');
    }
}
