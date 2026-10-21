<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\PublicCache;
use App\Support\Settings;
use App\Support\SiteUrl;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = PublicCache::remember('sitemap', function () {
            $pages = Page::live()->where('in_sitemap', true)->orderByRaw("CASE WHEN slug = '' THEN 0 ELSE 1 END")->orderBy('title')->get();
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($pages as $p) {
                if (! $p->isIndexable() || ($p->canonical_url && $p->canonical_url !== $p->url())) {
                    continue;
                }
                $out .= '  <url><loc>'.e($p->url()).'</loc><lastmod>'.$p->updated_at->toAtomString().'</lastmod></url>'."\n";
            }

            return $out.'</urlset>'."\n";
        }, 600);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }

    public function robots(): Response
    {
        $admin = '/'.trim(config('cms.admin_path', 'admin'), '/');
        $lines = ['User-agent: *'];
        if (Settings::get('discourage_indexing')) {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Disallow: '.$admin;
            $lines[] = 'Disallow: '.$admin.'/';
            $lines[] = 'Disallow: /forms/';
            foreach (preg_split('/\R/', (string) Settings::get('robots_txt')) as $l) {
                $l = trim(strip_tags($l));
                if ($l !== '' && preg_match('/^(User-agent|Disallow|Allow|Crawl-delay|#)/i', $l)) {
                    $lines[] = $l;
                }
            }
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.SiteUrl::to('/sitemap.xml');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
