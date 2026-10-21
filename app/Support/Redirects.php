<?php

namespace App\Support;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Managed URL redirects (Admin → SEO → Redirects), cached as a lookup map. */
class Redirects
{
    public static function map(): array
    {
        return PublicCache::remember('redirects', fn () => Redirect::where('is_enabled', true)
            ->get(['id', 'from_path', 'to_url', 'status_code'])
            ->mapWithKeys(fn ($r) => [$r->from_path => [$r->id, $r->to_url, $r->status_code]])->all());
    }

    public static function match(Request $request): ?RedirectResponse
    {
        $path = Redirect::normalise('/'.ltrim($request->path(), '/'));
        $hit = self::map()[$path] ?? null;
        if (! $hit) {
            return null;
        }
        [$id, $to, $code] = $hit;
        Redirect::whereKey($id)->update(['hits' => \DB::raw('hits + 1'), 'last_hit_at' => now()]);
        $target = SiteUrl::isSafe($to) ? SiteUrl::local($to) : url('/');
        if ($request->getQueryString() && ! str_contains($target, '?')) {
            $target .= (str_contains($target, '#') ? '' : '?'.$request->getQueryString());
        }

        return redirect()->to($target, in_array((int) $code, [301, 302, 307, 308], true) ? (int) $code : 301);
    }
}
