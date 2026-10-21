<?php

namespace App\Http\Controllers\Admin;

use App\Cms\SectionRegistry;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\PageController as SitePageController;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Media;
use App\Models\Page;
use App\Services\PageService;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private PageService $pages, private SectionRegistry $registry)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Page::class);
        $q = Page::query()->with('editor')->withCount('sections');
        if ($s = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%"));
        }
        match ($request->query('status')) {
            'published' => $q->live(),
            'scheduled' => $q->where('status', Page::STATUS_PUBLISHED)->where('published_at', '>', now()),
            'draft' => $q->where('status', Page::STATUS_DRAFT),
            'changes' => $q->whereNotNull('draft_updated_at'),
            default => null,
        };
        $pages = $q->orderByRaw("CASE WHEN slug = '' THEN 0 ELSE 1 END")->orderBy('title')->paginate(25)->withQueryString();

        return view('admin.pages.index', ['pages' => $pages, 'trashed' => Page::onlyTrashed()->count()]);
    }

    public function create(): View
    {
        $this->authorize('create', Page::class);
        $page = new Page(['template' => 'inner', 'robots' => 'index, follow', 'in_sitemap' => true]);
        $hero = $this->registry->has('inner-page-hero') ? [['id' => null, 'type' => 'inner-page-hero', 'anchor' => null, 'is_enabled' => true, 'data' => $this->registry->defaults('inner-page-hero')]] : [];

        return $this->editor($page, ['attrs' => $page->only(Page::CONTENT_FIELDS), 'sections' => $hero, 'from_draft' => false]);
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $publish = $request->input('action') === 'publish';
        if ($publish) {
            $this->authorize('publish', new Page());
        }
        $sections = $this->pages->cleanSections($request->input('sections'));
        $page = $this->pages->create($request->pageAttributes(null), $sections, $publish, $request->publishAt());

        return redirect()->route('admin.pages.edit', $page)->with('success', $publish ? 'Page created and published.' : 'Page saved as a draft.');
    }

    public function edit(Page $page): View
    {
        $this->authorize('update', $page);

        return $this->editor($page, $this->pages->editable($page));
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $publish = $request->input('action') === 'publish';
        if ($publish) {
            $this->authorize('publish', $page);
        }
        $sections = $this->pages->cleanSections($request->input('sections'));
        $wasPublished = $page->status === Page::STATUS_PUBLISHED;
        $this->pages->save($page, $request->pageAttributes($page), $sections, $publish, $request->publishAt(), $request->boolean('redirect_old_slug'));

        $msg = match (true) {
            $publish && $page->isScheduled() => 'Changes saved. The page is scheduled to go live on '.$page->published_at->timezone(\App\Support\Settings::get('timezone'))->format('d M Y, H:i').'.',
            $publish => 'Changes published.',
            $wasPublished => 'Changes saved as a draft. The live page is unchanged until the changes are published.',
            default => 'Draft saved.',
        };

        return redirect()->route('admin.pages.edit', $page)->with('success', $msg);
    }

    public function publish(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('publish', $page);
        if ($request->input('discard')) {
            $this->pages->discardDraft($page);

            return back()->with('success', 'Unpublished changes discarded.');
        }
        $this->pages->publish($page);

        return back()->with('success', 'Page published.');
    }

    public function unpublish(Page $page): RedirectResponse
    {
        $this->authorize('publish', $page);
        if ($page->isHome()) {
            return back()->with('error', 'The home page cannot be unpublished.');
        }
        $this->pages->unpublish($page);

        return back()->with('success', 'Page unpublished. It is no longer visible on the website.');
    }

    public function duplicate(Page $page): RedirectResponse
    {
        $this->authorize('create', Page::class);
        $copy = $this->pages->duplicate($page);

        return redirect()->route('admin.pages.edit', $copy)->with('success', 'Page duplicated as a draft. Change its title and URL before publishing.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorize('delete', $page);
        $this->pages->delete($page);

        return redirect()->route('admin.pages.index')->with('success', 'Page moved to the trash. Menu links to it were disabled.');
    }

    public function trash(): View
    {
        $this->authorize('viewAny', Page::class);

        return view('admin.pages.trash', ['pages' => Page::onlyTrashed()->latest('deleted_at')->paginate(25)]);
    }

    public function restore(int $id): RedirectResponse
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $page);
        $this->pages->restore($page);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page restored as a draft.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $page);
        \App\Support\AuditLogger::log('page.force_deleted', 'Permanently deleted page “'.$page->title.'”', $page);
        $page->forceDelete();

        return back()->with('success', 'Page permanently deleted.');
    }

    /** Preview unsaved editor content in a new tab (never cached, never indexed). */
    public function preview(PageRequest $request): Response
    {
        $existing = $request->filled('page_id') ? Page::find($request->integer('page_id')) : null;
        $page = $existing ? clone $existing : new Page();
        $page->fill($request->pageAttributes($existing));
        $sections = $this->pages->previewSections($this->pages->cleanSections($request->input('sections')));
        $data = app(SitePageController::class)->viewData($page, $sections, true);
        $data['previewBack'] = $existing ? route('admin.pages.edit', $existing) : route('admin.pages.index');

        return response()->view('site.page', $data)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Preview the saved version (including pending draft) of a page. */
    public function previewSaved(Page $page): Response
    {
        $this->authorize('update', $page);
        $content = $this->pages->editable($page);
        $p = clone $page;
        $p->fill(array_intersect_key($content['attrs'], array_flip(Page::CONTENT_FIELDS)));
        $data = app(SitePageController::class)->viewData($p, $this->pages->previewSections($content['sections']), true);
        $data['previewBack'] = route('admin.pages.edit', $page);

        return response()->view('site.page', $data)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** Default content for a section type added in the editor. */
    public function sectionDefaults(string $type): JsonResponse
    {
        $this->authorize('create', Page::class);
        abort_unless($this->registry->has($type), 404);

        return response()->json(['type' => $type, 'data' => $this->registry->defaults($type)]);
    }

    private function editor(Page $page, array $content): View
    {
        $mediaIds = array_merge(\App\Cms\MediaMap::idsIn($content['sections']), [$content['attrs']['featured_image_id'] ?? null, $content['attrs']['og_image_id'] ?? null]);
        $media = Media::with('variants')->whereIn('id', array_filter($mediaIds))->get()->mapWithKeys(fn ($m) => [$m->id => [
            'id' => $m->id, 'thumb' => $m->thumbUrl(), 'url' => $m->url(), 'alt' => $m->alt, 'filename' => $m->filename, 'width' => $m->width, 'height' => $m->height,
        ]]);
        $checklist = $page->exists ? Seo::checklist(tap($page->replicate(), fn ($r) => $r->id = $page->id)->fill(array_intersect_key($content['attrs'], array_flip(Page::CONTENT_FIELDS))), $this->pages->previewSections($content['sections'])) : [];

        return view('admin.pages.edit', [
            'page' => $page,
            'attrs' => $content['attrs'],
            'fromDraft' => $content['from_draft'],
            'sections' => $content['sections'],
            'types' => collect($this->registry->all())->map(fn ($t) => ['key' => $t['key'], 'label' => $t['label'], 'origin' => $t['origin'], 'fields' => $t['fields'], 'anchor' => $t['anchor'] ?? null])->all(),
            'groups' => $this->registry->grouped(),
            'media' => $media,
            'checklist' => $checklist,
            'pageOptions' => Page::orderBy('title')->get(['id', 'title', 'slug'])->map(fn ($p) => ['title' => $p->title, 'path' => $p->path()]),
        ]);
    }
}
