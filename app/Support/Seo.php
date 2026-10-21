<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Str;

/** Builds the <head> SEO data and JSON-LD for a public page. */
class Seo
{
    public string $title = '';
    public string $description = '';
    public string $robots = 'index, follow';
    public ?string $canonical = null;
    public string $ogTitle = '';
    public string $ogDescription = '';
    public string $ogImage = '';
    public array $jsonLd = [];

    public static function forPage(Page $page, iterable $sections = [], bool $preview = false): self
    {
        $s = new self();
        $set = Settings::all();
        $s->title = $page->meta_title ?: Str::limit(strip_tags($page->title).($set['seo_title_suffix'] ?? ''), 70, '');
        $s->description = $page->meta_description ?: (string) $set['seo_default_description'];
        $s->robots = $page->robots ?: 'index, follow';
        if ($preview || $set['discourage_indexing']) {
            $s->robots = 'noindex, nofollow';
        }
        $s->canonical = $page->canonical_url ?: $page->url();
        $s->ogTitle = $page->og_title ?: $s->title;
        $s->ogDescription = $page->og_description ?: $s->description;
        $s->ogImage = $page->ogImage ? Img::absolute(['media_id' => $page->og_image_id])
            : ($page->featuredImage ? Img::absolute(['media_id' => $page->featured_image_id]) : Img::absolute($set['og_default_image']));

        $home = SiteUrl::to('/');
        if ($page->isHome()) {
            $s->jsonLd[] = self::organisation();
            $s->jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $set['site_name'], 'url' => $home];
        } else {
            $s->jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => strip_tags($page->title), 'item' => $page->url()],
            ]];
        }
        $s->jsonLd[] = array_filter([
            '@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $s->title, 'description' => $s->description,
            'url' => $page->url(), 'inLanguage' => str_replace('_', '-', $set['locale']),
            'isPartOf' => ['@type' => 'WebSite', 'name' => $set['site_name'], 'url' => $home],
            'dateModified' => $page->updated_at?->toAtomString(),
        ]);
        // FAQPage only when an administrator explicitly enabled it for a genuine FAQ section
        $faq = [];
        foreach ($sections as $section) {
            $d = (array) $section->data;
            if ($section->is_enabled && ! empty($d['faq_schema'])) {
                foreach ((array) ($d['faq_items'] ?? []) as $q) {
                    $faq[] = ['@type' => 'Question', 'name' => trim(strip_tags((string) ($q['question'] ?? ''))),
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags((string) ($q['answer'] ?? ''), '<p><a><br><strong><em><ul><ol><li>'))]];
                }
            }
        }
        if ($faq) {
            $s->jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq];
        }

        return $s;
    }

    public static function simple(string $title, string $robots = 'noindex, follow'): self
    {
        $s = new self();
        $set = Settings::all();
        $s->title = $title.($set['seo_title_suffix'] ?? '');
        $s->description = (string) $set['seo_default_description'];
        $s->robots = $robots;
        $s->ogTitle = $s->title;
        $s->ogDescription = $s->description;
        $s->ogImage = Img::absolute($set['og_default_image']);

        return $s;
    }

    /** Organisation / Preschool schema built only from facts entered in Settings (no ratings or reviews). */
    public static function organisation(): array
    {
        $set = Settings::all();
        $sameAs = array_values(array_filter([$set['social_facebook'], $set['social_instagram'], $set['social_youtube'], $set['social_linkedin']]));
        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => trim((string) $set['contact_address']) ?: null,
            'addressLocality' => $set['contact_city'] ?: null,
            'addressRegion' => $set['contact_region'] ?: null,
            'addressCountry' => $set['contact_country'] ?: null,
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $set['org_type'] ?: 'Preschool',
            'name' => $set['site_name'],
            'url' => SiteUrl::to('/'),
            'logo' => Img::absolute($set['logo']) ?: null,
            'image' => Img::absolute($set['og_default_image']) ?: null,
            'email' => $set['contact_email'] ?: null,
            'telephone' => $set['contact_phone'] ?: null,
            'address' => count($address) > 1 ? $address : null,
            'sameAs' => $sameAs ?: null,
        ]);
    }

    /**
     * Editor checklist. Guidance only – it cannot guarantee rankings.
     *
     * @return array<int, array{ok:bool, level:string, text:string}>
     */
    public static function checklist(Page $page, iterable $sections): array
    {
        $title = $page->meta_title ?: $page->title.Settings::get('seo_title_suffix');
        $desc = (string) $page->meta_description;
        $out = [];
        $tl = mb_strlen($title);
        $out[] = ['ok' => $tl >= 30 && $tl <= 60, 'level' => $tl > 70 || $tl < 15 ? 'bad' : 'warn', 'text' => "SEO title length: {$tl} characters (aim for 30–60)."];
        $dl = mb_strlen($desc);
        $out[] = ['ok' => $dl >= 70 && $dl <= 160, 'level' => $dl === 0 ? 'bad' : 'warn', 'text' => $dl === 0 ? 'Meta description is empty (the site default will be used).' : "Meta description length: {$dl} characters (aim for 70–160)."];
        $out[] = ['ok' => true, 'level' => 'info', 'text' => $page->canonical_url ? 'Custom canonical URL set.' : 'Canonical URL: automatic ('.$page->url().').'];
        if ($page->canonical_url && ! preg_match('#^https?://#', $page->canonical_url)) {
            $out[] = ['ok' => false, 'level' => 'bad', 'text' => 'Canonical URL must be an absolute https:// address.'];
        }
        $out[] = ['ok' => ! str_contains((string) $page->robots, 'noindex'), 'level' => 'warn', 'text' => str_contains((string) $page->robots, 'noindex') ? 'This page is set to noindex and will not appear in search results.' : 'Page can be indexed.'];

        $missingAlt = 0;
        $h1 = 0;
        $words = 0;
        $walker = function ($fields, $data) use (&$walker, &$missingAlt, &$words) {
            foreach ($fields as $f) {
                $v = $data[$f['key']] ?? null;
                if ($f['type'] === 'image' && empty($f['decorative']) && is_array($v) && ($v['src'] || $v['media_id'])) {
                    $alt = trim((string) ($v['alt'] ?? ''));
                    if ($alt === '' && ! empty($v['media_id'])) {
                        $alt = (string) \App\Models\Media::whereKey($v['media_id'])->value('alt');
                    }
                    if ($alt === '') {
                        $missingAlt++;
                    }
                } elseif ($f['type'] === 'repeater') {
                    foreach ((array) $v as $item) {
                        $walker($f['fields'], (array) $item);
                    }
                } elseif (in_array($f['type'], ['inline', 'richtext', 'text'], true)) {
                    $words += str_word_count(strip_tags((string) $v));
                } elseif ($f['type'] === 'list') {
                    $words += str_word_count(strip_tags(implode(' ', (array) $v)));
                }
            }
        };
        $registry = app(\App\Cms\SectionRegistry::class);
        foreach ($sections as $sec) {
            $type = $registry->get($sec->type);
            if (! $type || ! $sec->is_enabled) {
                continue;
            }
            if (str_contains(\Illuminate\Support\Facades\File::get(resource_path('views/sections/'.$sec->type.'.blade.php')), '<h1')) {
                $h1++;
            }
            $walker($type['fields'], (array) $sec->data);
        }
        $out[] = ['ok' => $missingAlt === 0, 'level' => 'warn', 'text' => $missingAlt ? "{$missingAlt} content image(s) without alt text." : 'All content images have alt text.'];
        $out[] = ['ok' => $h1 === 1, 'level' => $h1 === 0 ? 'bad' : 'warn', 'text' => $h1 === 1 ? 'Exactly one main heading (H1).' : ($h1 === 0 ? 'No section with a main heading (H1) – add a hero section.' : "{$h1} sections contain an H1 – use only one hero section per page.")];
        $out[] = ['ok' => $words >= 250, 'level' => 'warn', 'text' => "About {$words} words of text on the page".($words < 250 ? ' – consider adding more helpful content.' : '.')];
        $dupe = Page::where('id', '!=', $page->id ?? 0)->whereNotNull('meta_title')->where('meta_title', $page->meta_title)->exists();
        if ($page->meta_title && $dupe) {
            $out[] = ['ok' => false, 'level' => 'bad', 'text' => 'Another page uses the same SEO title.'];
        }
        if ($page->meta_description && Page::where('id', '!=', $page->id ?? 0)->where('meta_description', $page->meta_description)->exists()) {
            $out[] = ['ok' => false, 'level' => 'bad', 'text' => 'Another page uses the same meta description.'];
        }

        return $out;
    }
}
