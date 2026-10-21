<?php

namespace App\Http\Controllers\Site;

use App\Cms\PageRenderer;
use App\Cms\SectionRegistry;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Redirects;
use App\Support\Seo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    public function __construct(private PageRenderer $renderer, private SectionRegistry $registry)
    {
    }

    public function show(Request $request, ?string $slug = null): Response
    {
        if ($redirect = Redirects::match($request)) {
            return $redirect;
        }
        $page = Page::live()->where('slug', $slug ?? '')->with(['sections', 'ogImage', 'featuredImage'])->first();
        if (! $page) {
            abort(404);
        }

        return response()->view('site.page', $this->viewData($page, $page->sections, false));
    }

    /** Any path that is not a page: managed redirects (incl. legacy *.html URLs) or 404. */
    public function fallback(Request $request): Response
    {
        if ($redirect = Redirects::match($request)) {
            return $redirect;
        }
        abort(404);
    }

    /** Shared by the live site and the admin preview. */
    public function viewData(Page $page, iterable $sections, bool $preview): array
    {
        $sections = collect($sections);
        $html = $preview
            ? $this->renderer->render($sections, strip_tags($page->title))
            : $this->renderer->renderCached($page);

        return [
            'html' => $html,
            'seo' => Seo::forPage($page, $sections, $preview),
            'css' => $this->renderer->cssFor($page, $sections),
            'bodyClass' => $page->template === 'home' ? null : 'inner pg-'.($page->slug ?: 'home'),
            'currentPath' => $page->path(),
            'currentPage' => $page,
            'preloadImage' => $page->template === 'home' ? $this->firstImage($sections) : null,
            'isPreview' => $preview,
        ];
    }

    private function firstImage($sections): ?string
    {
        $first = $sections->first(fn ($s) => $s->is_enabled);
        if (! $first || ! ($type = $this->registry->get($first->type))) {
            return null;
        }
        foreach ($type['fields'] as $f) {
            if ($f['type'] === 'image' && empty($f['decorative'])) {
                $v = $first->data[$f['key']] ?? [];
                if (! empty($v['media_id'])) {
                    return \App\Support\Img::url($v);
                }

                return ! empty($v['src']) ? asset($v['src']) : null;
            }
        }

        return null;
    }
}
