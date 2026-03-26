<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'type'];

    protected const CACHE_KEY = 'app_settings';

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::getAllCached();

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        return $settings[$key];
    }

    public static function set(string $key, mixed $value, ?string $type = null): void
    {
        if ($type === null) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'json',
                default => 'string',
            };
        }

        $storedValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue, 'type' => $type],
        );
    }

    public static function getAllCached(): array
    {
        return Cache::rememberForever(static::CACHE_KEY, function () {
            $settings = [];

            foreach (static::all() as $setting) {
                $settings[$setting->key] = $setting->castValue();
            }

            return $settings;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    public function castValue(): mixed
    {
        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
