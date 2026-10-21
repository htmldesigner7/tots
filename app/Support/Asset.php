<?php

namespace App\Support;

/** Versioned URLs for the design's static CSS/JS files (cache busting on deploy). */
class Asset
{
    public static function v(string $path): string
    {
        $file = public_path($path);
        $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';

        return asset($path).'?v='.$v;
    }
}
