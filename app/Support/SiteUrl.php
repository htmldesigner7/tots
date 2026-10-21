<?php

namespace App\Support;

class SiteUrl
{
    /** Configured public base URL without trailing slash (Settings → Website URL, else APP_URL). */
    public static function base(): string
    {
        $base = (string) (Settings::get('site_url') ?: config('app.url'));

        return rtrim($base, '/');
    }

    /** Absolute canonical URL for a site path (used for canonical, sitemap, OG, JSON-LD). */
    public static function to(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return self::base().'/'.ltrim($path, '/');
    }

    /**
     * URL for use in links on the current request. Root-relative paths are
     * resolved against the application URL (works when installed in a sub-folder);
     * anything else (#anchors, mailto:, tel:, https://) is returned unchanged.
     */
    public static function local(?string $url): string
    {
        $url = (string) $url;
        if ($url === '') {
            return '#';
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            [$path, $frag] = array_pad(explode('#', $url, 2), 2, null);

            return rtrim(url($path === '' ? '/' : $path), '/').($path === '/' ? '/' : '').($frag !== null ? '#'.$frag : '');
        }

        return $url;
    }

    /** Whether a user supplied URL uses a safe scheme. */
    public static function isSafe(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return true;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        if (str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return true;
        }

        return (bool) preg_match('#^(https?://[^\s]+|mailto:[^\s]+|tel:[+\d\s().-]+)$#i', $url);
    }
}
