<?php

namespace App\Support;

/**
 * Server checks shown on the admin dashboard. They explain, in plain words, the
 * usual reasons image uploads or images fail on a new server.
 */
class SystemCheck
{
    /** @return array<int,array{label:string,ok:bool,detail:string}> */
    public static function run(): array
    {
        $checks = [];

        $gd = extension_loaded('gd');
        $webp = $gd && (gd_info()['WebP Support'] ?? false);
        $checks[] = [
            'label' => 'Image processing (PHP GD)',
            'ok' => $gd && $webp,
            'detail' => ! $gd
                ? 'The "gd" extension is disabled, so images cannot be uploaded. Enable extension=gd in php.ini (or ask the host).'
                : ($webp ? 'Enabled, with WebP support.' : 'Enabled, but without WebP support. Ask the host to enable WebP in GD.'),
        ];

        $link = public_path('storage');
        $linked = is_dir($link) && realpath($link) === realpath(storage_path('app/public'));
        $checks[] = [
            'label' => 'Public storage link',
            'ok' => $linked,
            'detail' => $linked
                ? 'public/storage points to storage/app/public.'
                : 'The public/storage link is missing or broken, so images are served through the app (works, but slower). Fix: run "php artisan storage:link --force" on the server (or create the public/storage link in the host panel).',
        ];

        $writable = is_writable(storage_path('app/public')) && is_writable(storage_path('app/private'));
        $checks[] = [
            'label' => 'Upload folders writable',
            'ok' => $writable,
            'detail' => $writable ? 'storage/app/public and storage/app/private can be written to.' : 'Make storage/ and its sub-folders writable by the web server (permission 775).',
        ];

        $need = (int) config('cms.media.max_upload_kb') * 1024;
        $upload = self::bytes(ini_get('upload_max_filesize'));
        $post = self::bytes(ini_get('post_max_size'));
        $limitOk = $upload >= $need && $post >= $need;
        $checks[] = [
            'label' => 'PHP upload limits',
            'ok' => $limitOk,
            'detail' => 'upload_max_filesize '.ini_get('upload_max_filesize').', post_max_size '.ini_get('post_max_size')
                .($limitOk ? '.' : ' — below the '.round($need / 1048576).' MB allowed by the site; larger files will fail. Raise both in php.ini.'),
        ];

        $configured = parse_url((string) SiteUrl::base(), PHP_URL_HOST);
        $current = request()->getHost();
        $hostOk = ! $configured || $configured === $current;
        $checks[] = [
            'label' => 'Website URL',
            'ok' => $hostOk,
            'detail' => $hostOk
                ? 'Matches this address ('.$current.').'
                : 'Configured as "'.$configured.'" but you are on "'.$current.'". Set APP_URL (or Settings → Website URL) to the live address so links, sitemap and sharing images are correct.',
        ];

        $debug = (bool) config('app.debug') && app()->environment('production');
        $checks[] = [
            'label' => 'Debug mode',
            'ok' => ! $debug,
            'detail' => $debug ? 'APP_DEBUG is on in production; set APP_DEBUG=false in .env.' : 'Off for visitors (or running locally).',
        ];

        return $checks;
    }

    private static function bytes(string|false $v): int
    {
        $v = trim((string) $v);
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
