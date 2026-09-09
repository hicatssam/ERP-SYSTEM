<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $setting = static::query()
                ->where('key', $key)
                ->first();

            return $setting
                ? $setting->typedValue()
                : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value]
        );

        Cache::forget("setting:{$key}");
    }

    public static function flushCache(): void
    {
        static::query()
            ->pluck('key')
            ->each(fn (string $key) => Cache::forget("setting:{$key}"));
    }

    public static function assetUrl(string $key, ?string $fallback = null): ?string
    {
        $value = static::get($key);

        if (is_string($value) && trim($value) !== '') {
            return asset($value);
        }

        return $fallback ? asset($fallback) : null;
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            default   => $this->value,
        };
    }
}