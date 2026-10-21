<?php

namespace App\Support;

use App\Models\Menu;
use Illuminate\Support\Str;

/** Cached, render-ready menu trees for the public site. */
class Menus
{
    public static function menu(string $location): ?array
    {
        return PublicCache::remember('menu.'.$location, function () use ($location) {
            $menu = Menu::where('location', $location)->first();
            if (! $menu) {
                return null;
            }
            $items = $menu->items()->with('page')->where('is_enabled', true)->get();
            $build = function ($parentId) use (&$build, $items) {
                return $items->where('parent_id', $parentId)->sortBy('sort')->map(function ($i) use ($build) {
                    // links to unpublished pages are hidden from the public menu
                    if ($i->page_id && (! $i->page || ! $i->page->isLive())) {
                        return null;
                    }

                    return [
                        'label' => $i->label,
                        'href' => $i->href(),
                        'match' => $i->matchPath(),
                        'slug' => Str::slug($i->label) ?: 'item-'.$i->id,
                        'style' => (string) $i->style,
                        'target_attr' => $i->target === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '',
                        'children' => $build($i->id),
                    ];
                })->filter()->values()->all();
            };

            return ['title' => $menu->title, 'items' => $build(null)];
        }, 300); // short TTL so links to scheduled pages appear soon after they go live
    }

    public static function tree(string $location): array
    {
        return self::menu($location)['items'] ?? [];
    }

    public static function isCurrent(array $item, ?string $path): bool
    {
        return $path !== null && $item['match'] !== null && $item['match'] === $path;
    }

    public static function isActive(array $item, ?string $path): bool
    {
        if (self::isCurrent($item, $path)) {
            return true;
        }
        foreach ($item['children'] as $c) {
            if (self::isCurrent($c, $path)) {
                return true;
            }
        }

        return false;
    }
}
