<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\AuditLogger;
use App\Support\PublicCache;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        $this->authorize('menus.manage');
        foreach (config('cms.menu_locations') as $loc => $name) {
            Menu::firstOrCreate(['location' => $loc], ['name' => $name]);
        }

        return view('admin.menus.index', ['menus' => Menu::withCount('items')->get()->sortBy(fn ($m) => array_search($m->location, array_keys(config('cms.menu_locations'))))]);
    }

    public function edit(Menu $menu): View
    {
        $this->authorize('menus.manage');
        $items = $menu->items()->get();
        $tree = $items->whereNull('parent_id')->sortBy('sort')->map(fn ($i) => $this->node($i, $items))->values();

        return view('admin.menus.edit', [
            'menu' => $menu,
            'tree' => $tree,
            'pages' => Page::orderBy('title')->get(['id', 'title', 'slug', 'status']),
            'maxDepth' => $menu->location === 'header' ? 2 : 1,
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorize('menus.manage');
        $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'string', 'max:200000'],
        ]);
        $tree = json_decode($request->input('items'), true);
        if (! is_array($tree)) {
            throw ValidationException::withMessages(['items' => 'The menu could not be read. Please reload the page.']);
        }
        $maxDepth = $menu->location === 'header' ? 2 : 1;
        $pageIds = Page::pluck('id')->all();
        $errors = [];
        $count = 0;
        $clean = function (array $nodes, int $depth) use (&$clean, &$errors, &$count, $maxDepth, $pageIds) {
            $out = [];
            foreach ($nodes as $n) {
                if (++$count > 200) {
                    $errors[] = 'A menu can have at most 200 items.';
                    break;
                }
                $label = trim(strip_tags((string) ($n['label'] ?? '')));
                $pageId = isset($n['page_id']) && $n['page_id'] !== '' ? (int) $n['page_id'] : null;
                $url = trim((string) ($n['url'] ?? ''));
                if ($label === '' || mb_strlen($label) > 120) {
                    $errors[] = 'Every menu item needs a label (max 120 characters).';
                }
                if ($pageId && ! in_array($pageId, $pageIds, true)) {
                    $errors[] = "“{$label}”: the selected page no longer exists.";
                    $pageId = null;
                }
                if ($pageId && $url !== '' && ! str_starts_with($url, '#')) {
                    $errors[] = "“{$label}”: when a page is selected, the URL field may only contain a #section anchor.";
                }
                if (! $pageId && $url === '') {
                    $errors[] = "“{$label}”: choose a page or enter a URL.";
                }
                if ($url !== '' && (! SiteUrl::isSafe($url) || mb_strlen($url) > 500)) {
                    $errors[] = "“{$label}”: the URL must start with /, #, https://, http://, mailto: or tel:.";
                }
                $children = (array) ($n['children'] ?? []);
                if ($children && $depth >= $maxDepth) {
                    $errors[] = $maxDepth === 1 ? 'Footer menus cannot have dropdown items.' : 'Menus can only be nested one level deep.';
                    $children = [];
                }
                $out[] = [
                    'label' => $label, 'page_id' => $pageId, 'url' => $url ?: null,
                    'target' => ($n['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
                    'style' => array_key_exists((string) ($n['style'] ?? ''), config('cms.menu_styles')) ? ((string) ($n['style'] ?? '') ?: null) : null,
                    'is_enabled' => filter_var($n['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'children' => $clean($children, $depth + 1),
                ];
            }

            return $out;
        };
        $items = $clean($tree, 1);
        if ($errors) {
            throw ValidationException::withMessages(['items' => array_values(array_unique($errors))]);
        }

        DB::transaction(function () use ($menu, $items, $request) {
            $menu->title = strip_tags((string) $request->input('title')) ?: null;
            $menu->save();
            $menu->items()->delete();
            $save = function (array $nodes, ?int $parent) use (&$save, $menu) {
                foreach ($nodes as $sort => $n) {
                    $item = MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent, 'sort' => $sort] + collect($n)->except('children')->all());
                    $save($n['children'], $item->id);
                }
            };
            $save($items, null);
        });
        PublicCache::flush();
        AuditLogger::log('menu.updated', 'Updated the menu “'.$menu->name.'”', $menu, ['items' => $count]);

        return back()->with('success', 'Menu saved.');
    }

    private function node(MenuItem $i, $all): array
    {
        return [
            'label' => $i->label, 'page_id' => $i->page_id, 'url' => $i->url, 'target' => $i->target,
            'style' => $i->style, 'is_enabled' => $i->is_enabled,
            'children' => $all->where('parent_id', $i->id)->sortBy('sort')->map(fn ($c) => $this->node($c, $all))->values()->all(),
        ];
    }
}
