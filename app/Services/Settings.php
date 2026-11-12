<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    private const CACHE_KEY = 'settings.all';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a setting value, falling back to config('linkcash.defaults') and then $default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $stored = self::all();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return config('linkcash.defaults.'.$key, $default);
    }

    /**
     * Store a setting value and clear the cached table.
     */
    public static function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get a setting value as an integer.
     */
    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /**
     * @return array<string, string|null>
     */
    private static function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
