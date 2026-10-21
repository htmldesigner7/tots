<?php

namespace App\Cms;

use App\Models\Page;
use App\Support\PublicCache;
use Illuminate\Support\Collection;

/**
 * Renders a page's enabled sections to HTML. Used for the live site (cached
 * per page and content version) and for previews (never cached).
 */
class PageRenderer
{
    public function __construct(private SectionRegistry $registry)
    {
    }

    /**
     * @param  Collection<int, \App\Models\PageSection>|array  $sections
     */
    public function render(iterable $sections, string $pageTitle = '', bool $cache = false, ?int $pageId = null): string
    {
        $sections = collect($sections)->filter(fn ($s) => $s->is_enabled && $this->registry->has($s->type))->values();
        $media = new MediaMap(MediaMap::idsIn($sections->map(fn ($s) => $s->data)->all()));
        $seen = [];
        $html = '';
        foreach ($sections as $section) {
            $type = $this->registry->get($section->type);
            $n = $seen[$section->type] = ($seen[$section->type] ?? 0) + 1;
            $uid = $n > 1 ? '-'.$n : '';
            $anchor = $section->anchor ?: (($type['anchor'] ?? null) ? $type['anchor'].$uid : 's'.($section->id ?: $n).'-'.$section->type);
            $view = new SectionView((array) $section->data, $type['fields'], $media, $uid, $anchor);
            $make = fn () => view($type['view'], ['s' => $view, 'crumbTitle' => $pageTitle])->render()."\n";
            // sections with forms (CSRF token, validation state) are always rendered live
            $html .= ($cache && $section->id && empty($type['dynamic']))
                ? PublicCache::remember('sec.'.$pageId.'.'.$section->id.'.'.$n.'.'.md5($pageTitle), $make)
                : $make();
        }

        return $html;
    }

    public function renderCached(Page $page): string
    {
        return $this->render($page->sections, strip_tags($page->title), true, $page->id);
    }

    /** CSS bundles needed by the sections used on a page. */
    public function cssFor(Page $page, iterable $sections): array
    {
        $needsInner = $page->template !== 'home' || collect($sections)->contains(fn ($s) => ($this->registry->get($s->type)['css'] ?? 'inner') === 'inner');

        return $needsInner ? ['inner'] : [];
    }
}
