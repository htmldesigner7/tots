<?php

namespace App\Support;

use App\Models\Media;

/** Image helpers for setting-level images (logo, favicon, default share image). */
class Img
{
    public static function media(array|null $value): ?Media
    {
        $id = $value['media_id'] ?? null;

        return $id ? PublicCache::remember('media.'.$id, fn () => Media::with('variants')->find($id)) : null;
    }

    public static function url(array|null $value, bool $absolute = false): string
    {
        if ($m = self::media($value)) {
            $u = $m->url();
        } elseif (! empty($value['src'])) {
            $u = asset($value['src']);
        } else {
            return '';
        }

        return $absolute && ! preg_match('#^https?://#', $u) ? SiteUrl::to($u) : $u;
    }

    /** Absolute URL for social sharing meta (based on the configured site URL). */
    public static function absolute(array|null $value): string
    {
        if ($m = self::media($value)) {
            return SiteUrl::to('/'.ltrim(\Illuminate\Support\Str::after($m->url(), url('/')), '/'));
        }

        return ! empty($value['src']) ? SiteUrl::to('/'.ltrim($value['src'], '/')) : '';
    }

    public static function attrs(array|null $value, int $w, int $h): string
    {
        $m = self::media($value);
        $src = self::url($value);
        if ($m && $m->width && $m->height) {
            $h = (int) round($w * $m->height / $m->width);
        }

        return 'src="'.e($src).'" width="'.$w.'" height="'.$h.'" alt="'.e($value['alt'] ?? '').'"';
    }
}
