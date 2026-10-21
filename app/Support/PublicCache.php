<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Version counter used as part of every public-content cache key. Bumping it
 * invalidates all cached page data, menus and the sitemap at once without
 * needing a taggable cache store.
 */
class PublicCache
{
    public static function version(): int
    {
        return (int) Cache::rememberForever('cms.public_version', fn () => 1);
    }

    public static function key(string $name): string
    {
        // Rendered HTML embeds absolute asset URLs, so the key is scoped to the site URL;
        // a database moved between local and live must not serve the other's cached links.
        return 'cms.v'.self::version().'.'.substr(md5(url('/')), 0, 8).'.'.$name;
    }

    public static function remember(string $name, \Closure $callback, int $ttl = 86400): mixed
    {
        return Cache::remember(self::key($name), $ttl, $callback);
    }

    public static function flush(): void
    {
        Cache::forever('cms.public_version', self::version() + 1);
    }
}
