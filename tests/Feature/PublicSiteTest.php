<?php

namespace Tests\Feature;

use App\Models\Page;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    public static function pages(): array
    {
        return [
            ['/', 'Play-Based Preschool in Ahmedabad | Ananya Tots'],
            ['/about', 'About Ananya Tots | Our Vision, Mission &amp; Philosophy'],
            ['/programs', null], ['/learning', null], ['/learning-approach', null], ['/campus-life', null],
            ['/life-at-tots', null], ['/facilities', null], ['/parents', null], ['/parents-corner', null],
            ['/admissions', null], ['/careers', null], ['/contact', null],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_converted_page_renders(string $path, ?string $title): void
    {
        $res = $this->get($path)->assertOk();
        $html = $res->getContent();
        if ($title) {
            $this->assertStringContainsString("<title>{$title}</title>", $html);
        }
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost'.($path === '/' ? '/' : $path).'">', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'exactly one H1');
        // no Blade directive may leak into the output
        $this->assertDoesNotMatchRegularExpression('/@(if|endif|foreach|endforeach|php|include)\b/', $html);
        $this->assertStringContainsString('class="site-header"', $html);
        $this->assertStringContainsString('class="site-footer"', $html);
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $res->headers->get('Content-Security-Policy'));
    }

    public function test_header_menu_marks_current_page(): void
    {
        $html = $this->get('/life-at-tots')->getContent();
        $this->assertStringContainsString('<li class="has-sub is-active"><a href="http://localhost/campus-life">Campus Life</a>', $html);
        $this->assertStringContainsString('<a href="http://localhost/life-at-tots" aria-current="page">Life at Tots</a>', $html);
    }

    public function test_legacy_html_urls_redirect_permanently(): void
    {
        $this->get('/about.html')->assertStatus(301)->assertRedirect('http://localhost/about');
        $this->get('/index.html')->assertStatus(301)->assertRedirect('http://localhost/');
        $this->assertSame(1, \App\Models\Redirect::where('from_path', '/about.html')->value('hits'));
    }

    public function test_custom_404_is_noindex(): void
    {
        $res = $this->get('/no-such-page')->assertNotFound();
        $res->assertSee('Went Out to Play', false);
        $res->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_sitemap_lists_only_live_indexable_pages(): void
    {
        Page::where('slug', 'careers')->update(['status' => 'draft']);
        Page::where('slug', 'facilities')->update(['robots' => 'noindex, follow']);
        Page::where('slug', 'parents')->update(['published_at' => now()->addDay()]);
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('<loc>http://localhost/about</loc>', $xml);
        $this->assertStringContainsString('<loc>http://localhost/</loc>', $xml);
        $this->assertStringNotContainsString('/careers<', $xml);
        $this->assertStringNotContainsString('/facilities<', $xml);
        $this->assertStringNotContainsString('/parents<', $xml);
    }

    public function test_robots_txt_blocks_admin_and_lists_sitemap(): void
    {
        $txt = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString("Disallow: /admin\n", $txt);
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $txt);
    }

    public function test_draft_and_scheduled_pages_are_not_public(): void
    {
        Page::where('slug', 'careers')->update(['status' => 'draft']);
        $this->get('/careers')->assertNotFound();
        Page::where('slug', 'careers')->update(['status' => 'published', 'published_at' => now()->addHour()]);
        $this->get('/careers')->assertNotFound();
        $this->travel(2)->hours();
        $this->get('/careers')->assertOk();
    }

    public function test_structured_data_is_present_and_has_no_reviews(): void
    {
        $home = $this->get('/')->getContent();
        $this->assertStringContainsString('"@type":"Preschool"', $home);
        $this->assertStringNotContainsString('aggregateRating', $home);
        $this->assertStringNotContainsString('"Review"', $home);
        $about = $this->get('/about')->getContent();
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $about);
        $this->assertStringNotContainsString('FAQPage', $this->get('/parents')->getContent());
    }

    public function test_admin_is_never_indexed(): void
    {
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
