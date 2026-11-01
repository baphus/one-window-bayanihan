<?php

namespace App\Settings;

/**
 * One system setting, declared once.
 *
 * The page, the validator, the audit hook and the seeder all read these
 * declarations instead of restating them. The point is not abstraction for its
 * own sake: this codebase accumulated settings that nothing read
 * (two_factor_required, otp_expiry_seconds), and the `consumer` property makes
 * that impossible to add silently — SettingRegistryTest fails any entry that
 * declares no consumer.
 */
final class Setting
{
    public const TYPE_BOOL = 'bool';

    public const TYPE_INT = 'int';

    /**
     * @param  string  $key  The system_settings.key.
     * @param  string  $type  One of the TYPE_* constants.
     * @param  string  $group  UI grouping (see SettingGroup).
     * @param  mixed  $default  Used when no row exists.
     * @param  string|null  $envFallback  Env var this value falls back to, if any.
     * @param  bool  $confirmRequired  Destructive or security impact: confirm before saving.
     * @param  string  $consumer  Where the value is read. Never null — see class docblock.
     * @param  array{min?: int, max?: int}|null  $bounds  Numeric bounds for int settings.
     * @param  string  $description  One line shown under the field.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $group,
        public readonly mixed $default,
        public readonly ?string $envFallback,
        public readonly bool $confirmRequired,
        public readonly string $consumer,
        public readonly ?array $bounds,
        public readonly string $description,
    ) {}

    /**
     * A boolean setting.
     */
    public static function bool(string $key, string $group, bool $default, string $consumer, string $description, ?string $envFallback = null): self
    {
        return new self($key, self::TYPE_BOOL, $group, $default, $envFallback, false, $consumer, null, $description);
    }

    /**
     * An integer setting, optionally bounded.
     */
    public static function int(
        string $key,
        string $group,
        int $default,
        int $min,
        int $max,
        string $consumer,
        string $description,
        ?string $envFallback = null,
        bool $confirmRequired = false,
    ): self {
        return new self($key, self::TYPE_INT, $group, $default, $envFallback, $confirmRequired, $consumer, ['min' => $min, 'max' => $max], $description);
    }

    /**
     * Laravel validation rules derived from the declared type.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        // `nullable` first: an absent field is normal (it means "leave this
        // alone"), and ConvertEmptyStringsToNull delivers '' as null. Without
        // it, clearing one field fails the whole save.
        return match ($this->type) {
            self::TYPE_BOOL => ['nullable', 'boolean'],
            self::TYPE_INT => ['nullable', 'integer', 'min:'.$this->bounds['min'], 'max:'.$this->bounds['max']],
            default => ['nullable', 'string', 'max:255'],
        };
    }

    /**
     * Coerce a submitted or stored value to the declared type.
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }

        return match ($this->type) {
            self::TYPE_BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INT => (int) $value,
            default => (string) $value,
        };
    }

    /**
     * The raw value to store in system_settings.value.
     */
    public function serialize(mixed $value): string
    {
        if ($this->type === self::TYPE_BOOL) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * The page payload for this setting.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'group' => $this->group,
            'default' => $this->default,
            'env_fallback' => $this->envFallback,
            'confirm_required' => $this->confirmRequired,
            'bounds' => $this->bounds,
            'description' => $this->description,
        ];
    }
}
