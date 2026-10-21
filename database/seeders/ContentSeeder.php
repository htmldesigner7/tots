<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use App\Support\PublicCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports the approved static website: every page with its design-matched
 * sections (data/pages.json, generated from the HTML), the header/footer
 * menus, and 301 redirects from the old *.html addresses.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $pages = json_decode(file_get_contents(__DIR__.'/data/pages.json'), true);
            $ids = [];
            foreach ($pages as $p) {
                $page = Page::withTrashed()->where('slug', $p['slug'])->first();
                if ($page) {
                    $ids[$p['file']] = $page->id;   // never overwrite content edited in the CMS
                    continue;
                }
                $page = Page::create([
                    'title' => $p['title'],
                    'slug' => $p['slug'],
                    'template' => $p['template'],
                    'status' => Page::STATUS_PUBLISHED,
                    'published_at' => now(),
                    'meta_title' => $p['meta_title'],
                    'meta_description' => $p['meta_description'],
                    'robots' => $p['robots'],
                ]);
                foreach ($p['sections'] as $i => $s) {
                    $page->sections()->create([
                        'type' => $s['type'], 'anchor' => $s['anchor'], 'data' => $s['data'],
                        'sort' => $i, 'is_enabled' => true,
                    ]);
                }
                $ids[$p['file']] = $page->id;
            }

            $this->menus($ids);

            foreach ($pages as $p) {
                $from = '/'.$p['file'].'.html';
                Redirect::firstOrCreate(['from_path' => $from], ['to_url' => $p['slug'] === '' ? '/' : '/'.$p['slug'], 'status_code' => 301]);
            }
        });
        PublicCache::flush();
    }

    private function menus(array $ids): void
    {
        $menus = [
            'header' => ['Header navigation', null, [
                ['About', 'about'], ['Programmes', 'programs'],
                ['Learning', 'learning', [['Learning', 'learning'], ['Learning Approach', 'learning-approach']]],
                ['Campus Life', 'campus-life', [['Campus Life', 'campus-life'], ['Life at Tots', 'life-at-tots'], ['Facilities', 'facilities']]],
                ['Parents', 'parents', [['Parents', 'parents'], ['Parents’ Corner', 'parents-corner']]],
                ['Admissions', 'admissions'], ['Careers', 'careers'], ['Contact Us', 'contact'],
            ]],
            'footer-1' => ['Footer column 1', 'Our Little World', [
                ['About Ananya Tots', 'about'], ['Our Programmes', 'programs'], ['Learning Approach', 'learning-approach'],
                ['Learning Spaces', 'facilities'], ['Careers', 'careers'],
            ]],
            'footer-2' => ['Footer column 2', 'Come Closer', [
                ['A Day at Tots', 'life-at-tots'], ['Parent Partnership', 'parents'], ['Campus Life', 'campus-life'],
                ['Events & Celebrations', 'campus-life#celebrations'],
            ]],
            'footer-3' => ['Footer column 3', 'Let’s Begin', [
                ['Admissions', 'admissions'], ['Book a Visit', 'contact#enquiry', [], 'visit'], ['Admission Enquiry', 'admissions#enquiry'],
                ['Parents’ Corner', 'parents-corner'], ['Parent Questions', 'parents#faq'],
            ]],
        ];
        foreach ($menus as $location => [$name, $title, $items]) {
            $menu = Menu::firstOrCreate(['location' => $location], ['name' => $name, 'title' => $title]);
            if ($menu->items()->exists()) {
                continue;
            }
            $this->items($menu, $items, null, $ids);
        }
    }

    private function items(Menu $menu, array $items, ?int $parent, array $ids): void
    {
        foreach ($items as $sort => $it) {
            [$label, $target] = $it;
            $children = $it[2] ?? [];
            $style = $it[3] ?? null;
            $pageId = null;
            $url = null;
            if (str_starts_with($target, '#')) {
                $url = $target;
            } else {
                [$file, $frag] = array_pad(explode('#', $target, 2), 2, null);
                $pageId = $ids[$file] ?? null;
                $url = $frag ? '#'.$frag : null;
            }
            $item = $menu->items()->create([
                'parent_id' => $parent, 'label' => $label, 'page_id' => $pageId, 'url' => $url,
                'target' => '_self', 'style' => $style, 'is_enabled' => true, 'sort' => $sort,
            ]);
            if ($children) {
                $this->items($menu, $children, $item->id, $ids);
            }
        }
    }
}
