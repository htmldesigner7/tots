<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Website-wide settings with defaults from config/cms.php, cached for the
 * public site and refreshed whenever an administrator saves them.
 */
class Settings
{
    public const CACHE_KEY = 'cms.settings';

    private static ?array $loaded = null;

    public static function schema(): array
    {
        $flat = [];
        foreach (config('cms.settings') as $group => $fields) {
            foreach ($fields as $key => $def) {
                $flat[$key] = $def + ['group' => $group];
            }
        }

        return $flat;
    }

    public static function all(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                if (! Schema::hasTable('settings')) {
                    return [];
                }
            } catch (\Throwable) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')
                ->map(fn ($v) => json_decode($v, true))
                ->all();
        });
        $values = [];
        foreach (self::schema() as $key => $def) {
            $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : ($def['default'] ?? null);
        }

        return self::$loaded = $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return $all[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
        self::flush();
    }

    /** Lines of a textarea setting, trimmed and without blanks. */
    public static function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) self::get($key, '')))));
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$loaded = null;
        PublicCache::flush();
    }
}
