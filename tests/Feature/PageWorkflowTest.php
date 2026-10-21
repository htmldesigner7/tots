<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Page;
use App\Models\Redirect;
use Tests\TestCase;

class PageWorkflowTest extends TestCase
{
    private function payload(Page $page, array $overrides = [], ?callable $mutate = null): array
    {
        $sections = $page->sections->map(fn ($s) => ['id' => $s->id, 'type' => $s->type, 'anchor' => $s->anchor, 'is_enabled' => $s->is_enabled, 'data' => $s->data])->all();
        if ($mutate) {
            $sections = $mutate($sections);
        }

        return $overrides + [
            'title' => $page->title, 'slug' => $page->slug, 'template' => $page->template, 'robots' => $page->robots,
            'meta_title' => $page->meta_title, 'meta_description' => $page->meta_description, 'in_sitemap' => 1,
            'sections' => json_encode($sections), 'action' => 'publish',
        ];
    }

    public function test_create_draft_then_publish_new_page(): void
    {
        $this->admin();
        $res = $this->post('/admin/pages', [
            'title' => 'Summer Camp', 'slug' => '', 'template' => 'inner', 'robots' => 'index, follow',
            'sections' => json_encode([['type' => 'inner-page-hero', 'data' => app(\App\Cms\SectionRegistry::class)->defaults('inner-page-hero')]]),
            'action' => 'save',
        ]);
        $page = Page::where('slug', 'summer-camp')->firstOrFail();
        $res->assertRedirect("/admin/pages/{$page->id}/edit");
        $this->assertSame('draft', $page->status);
        $this->get('/summer-camp')->assertNotFound();
        $this->post("/admin/pages/{$page->id}/publish")->assertRedirect();
        $this->get('/summer-camp')->assertOk()->assertSee('<span aria-current="page">Summer Camp</span>', false);
    }

    public function test_editing_content_updates_frontend(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, [], function ($s) {
            $s[0]['data']['heading_first_line'] = 'A Brand New <em>Heading</em>';

            return $s;
        }))->assertRedirect();
        $this->get('/about')->assertSee('A Brand New <em>Heading</em>', false);
        $this->assertTrue(AuditLog::where('action', 'page.published')->where('subject_id', $page->id)->exists());
    }

    public function test_section_html_is_sanitised_and_unsafe_links_rejected(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, [], function ($s) {
            $s[0]['data']['heading_first_line'] = 'Hi<script>alert(1)</script><img src=x onerror=alert(2)><b onclick="x()">there</b>';

            return $s;
        }))->assertRedirect();
        $html = $this->get('/about')->getContent();
        $this->assertStringContainsString('Hi<b>there</b>', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<script>alert', $html);

        $this->put("/admin/pages/{$page->id}", $this->payload($page->fresh(), [], function ($s) {
            $s[0]['data']['button']['url'] = 'javascript:alert(1)';

            return $s;
        }))->assertSessionHasErrors('sections');
        $this->put("/admin/pages/{$page->id}", $this->payload($page->fresh(), [], fn ($s) => array_merge($s, [['type' => 'does-not-exist', 'data' => []]])))
            ->assertSessionHasErrors('sections');
        $this->put("/admin/pages/{$page->id}", $this->payload($page->fresh(), [], function ($s) {
            $s[0]['data']['image']['src'] = '../../.env';

            return $s;
        }))->assertSessionHasErrors('sections');
    }

    public function test_published_page_edits_are_drafts_until_published(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, ['action' => 'save'], function ($s) {
            $s[0]['data']['heading_first_line'] = 'Pending change';

            return $s;
        }))->assertSessionHas('success');
        $this->get('/about')->assertDontSee('Pending change');
        $this->get("/admin/pages/{$page->id}/preview")->assertOk()->assertSee('Pending change')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get("/admin/pages/{$page->id}/edit")->assertSee('unpublished changes');
        $this->post("/admin/pages/{$page->id}/publish")->assertRedirect();
        $this->get('/about')->assertSee('Pending change');
        $this->assertFalse($page->fresh()->hasDraft());
    }

    public function test_discard_draft(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, ['action' => 'save', 'title' => 'Draft title']));
        $this->post("/admin/pages/{$page->id}/publish", ['discard' => 1]);
        $this->assertFalse($page->fresh()->hasDraft());
        $this->assertSame('About Us', $page->fresh()->title);
    }

    public function test_unsaved_preview_does_not_persist(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->put('/admin/pages/preview', $this->payload($page, ['action' => 'preview', 'page_id' => $page->id], function ($s) {
            $s[0]['data']['heading_first_line'] = 'Only in preview';

            return $s;
        }))->assertOk()->assertSee('Only in preview')->assertSee('noindex, nofollow', false);
        $this->get('/about')->assertDontSee('Only in preview');
    }

    public function test_reorder_disable_and_duplicate_sections(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, [], function ($s) {
            [$s[1], $s[2]] = [$s[2], $s[1]];
            $s[3]['is_enabled'] = false;
            $copy = $s[5];
            $copy['id'] = null;
            $s[] = $copy;

            return $s;
        }))->assertRedirect();
        $html = $this->get('/about')->getContent();
        $this->assertLessThan(strpos($html, 'It Begins With How'), strpos($html, 'Our Shared Vision'));
        $this->assertStringNotContainsString('How Our Inspirations', $html);
        $this->assertSame(2, substr_count($html, 'Our Principal'));
        $this->assertSame(8, $page->sections()->count());
    }

    public function test_slug_change_creates_redirect(): void
    {
        $this->admin();
        $page = Page::where('slug', 'careers')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, ['slug' => 'jobs', 'redirect_old_slug' => 1]))->assertRedirect();
        $this->get('/jobs')->assertOk();
        $this->get('/careers')->assertStatus(301)->assertRedirect('http://localhost/jobs');
        // the legacy careers.html redirect keeps working (chain shortened)
        $this->assertSame('/jobs', Redirect::where('from_path', '/careers.html')->value('to_url'));
        // menus follow the page automatically
        $this->get('/')->assertSee('href="http://localhost/jobs"', false);
    }

    public function test_slug_validation(): void
    {
        $this->admin();
        $page = Page::where('slug', 'careers')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, ['slug' => 'about']))->assertSessionHasErrors('slug');
        $this->put("/admin/pages/{$page->id}", $this->payload($page, ['slug' => 'admin']))->assertSessionHasErrors('slug');
    }

    public function test_schedule_publication(): void
    {
        $this->admin();
        $page = Page::where('slug', 'careers')->first();
        $page->update(['status' => 'draft']);
        $when = now()->addDays(2)->timezone('Asia/Kolkata')->format('Y-m-d H:i');
        $this->put("/admin/pages/{$page->id}", $this->payload($page, ['publish_at' => $when]))->assertSessionHas('success');
        $this->assertTrue($page->fresh()->isScheduled());
        $this->get('/careers')->assertNotFound();
        $this->travel(3)->days();
        $this->get('/careers')->assertOk();
    }

    public function test_duplicate_delete_restore(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->post("/admin/pages/{$page->id}/duplicate")->assertRedirect();
        $copy = Page::where('slug', 'about-copy')->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertSame($page->sections()->count(), $copy->sections()->count());
        $this->assertNull($copy->meta_title);

        $this->delete("/admin/pages/{$page->id}")->assertRedirect('/admin/pages');
        $this->assertSoftDeleted($page);
        $this->get('/about')->assertNotFound();
        $this->assertStringNotContainsString('>About</a>', $this->get('/')->getContent());
        $this->post("/admin/pages/{$page->id}/restore")->assertRedirect();
        $this->assertSame('about', $page->fresh()->slug);
        $this->assertSame('draft', $page->fresh()->status);
    }

    public function test_home_page_cannot_be_deleted_or_unpublished(): void
    {
        $this->admin();
        $home = Page::where('slug', '')->first();
        $this->delete("/admin/pages/{$home->id}")->assertForbidden();
        $this->post("/admin/pages/{$home->id}/unpublish")->assertSessionHas('error');
        $this->get('/')->assertOk();
    }

    public function test_seo_fields_render(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, [
            'meta_title' => 'Custom Title', 'meta_description' => 'Custom description for the about page.',
            'canonical_url' => 'https://www.ananyatots.com/about', 'robots' => 'noindex, follow', 'og_title' => 'Share me',
        ]))->assertRedirect();
        $html = $this->get('/about')->getContent();
        $this->assertStringContainsString('<title>Custom Title</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Custom description for the about page.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://www.ananyatots.com/about">', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Share me">', $html);
        $this->assertStringNotContainsString('/about<', $this->get('/sitemap.xml')->getContent());
    }

    public function test_faq_schema_only_when_enabled(): void
    {
        $this->admin();
        $page = Page::where('slug', 'parents')->first();
        $this->put("/admin/pages/{$page->id}", $this->payload($page, [], function ($s) {
            foreach ($s as &$sec) {
                if (isset($sec['data']['faq_items'])) {
                    $sec['data']['faq_schema'] = true;
                }
            }

            return $s;
        }))->assertRedirect();
        $html = $this->get('/parents')->getContent();
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('How can I participate in school life?', $html);
    }
}
