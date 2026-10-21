<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FormSubmission;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    public function test_menu_update_with_dropdown_reflects_on_site(): void
    {
        $this->admin();
        $menu = Menu::where('location', 'header')->first();
        $about = Page::where('slug', 'about')->value('id');
        $items = [
            ['label' => 'Who We Are', 'page_id' => $about, 'url' => '', 'target' => '_self', 'is_enabled' => true, 'children' => [
                ['label' => 'Our Story', 'page_id' => $about, 'url' => '#story', 'is_enabled' => true, 'children' => []],
            ]],
            ['label' => 'Instagram', 'page_id' => null, 'url' => 'https://instagram.com/x', 'target' => '_blank', 'is_enabled' => true, 'children' => []],
            ['label' => 'Hidden', 'page_id' => null, 'url' => '/contact', 'is_enabled' => false, 'children' => []],
        ];
        $this->put("/admin/menus/{$menu->id}", ['items' => json_encode($items)])->assertSessionHas('success');
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<a href="http://localhost/about">Who We Are</a><button class="sub-toggle"', $html);
        $this->assertStringContainsString('<a href="http://localhost/about#story">Our Story</a>', $html);
        $this->assertStringContainsString('href="https://instagram.com/x" target="_blank" rel="noopener noreferrer">Instagram', $html);
        $this->assertStringNotContainsString('>Hidden<', $html);
    }

    public function test_menu_validation(): void
    {
        $this->admin();
        $menu = Menu::where('location', 'footer-1')->first();
        $this->put("/admin/menus/{$menu->id}", ['title' => 'T', 'items' => json_encode([['label' => 'X', 'url' => 'javascript:alert(1)', 'children' => []]])])->assertSessionHasErrors('items');
        $this->put("/admin/menus/{$menu->id}", ['title' => 'T', 'items' => json_encode([['label' => 'X', 'url' => '/a', 'children' => [['label' => 'Y', 'url' => '/b']]]])])->assertSessionHasErrors('items');
    }

    public function test_settings_update_global_content(): void
    {
        $this->admin();
        $this->put('/admin/settings', [
            'site_name' => 'Ananya Tots', 'timezone' => 'Asia/Kolkata', 'locale' => 'en_IN', 'theme_color' => '#673893',
            'header_cta_label' => 'Visit Us', 'header_cta_url' => '/contact#enquiry', 'copyright' => '© {year} Tots',
            'contact_phone' => '+91 90000 11111', 'contact_email' => 'hi@example.test', 'org_type' => 'Preschool',
            'enquiry_centres' => "Centre 1\nCentre 2", 'enquiry_programmes' => "Nursery", 'careers_positions' => 'Teacher',
            'announcement_enabled' => '1', 'announcement_text' => 'Admissions open!',
        ])->assertSessionHas('success');
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('>Visit Us</a>', $html);
        $this->assertStringContainsString('+91 90000 11111', $html);
        $this->assertStringContainsString('© '.now()->year.' Tots', $html);
        $this->assertStringContainsString('Admissions open!', $html);
        $this->assertStringContainsString('"telephone":"+91 90000 11111"', $html);
        $this->assertTrue(AuditLog::where('action', 'settings.updated')->exists());
    }

    public function test_settings_reject_unsafe_links(): void
    {
        $this->admin();
        $this->put('/admin/settings', ['site_name' => 'A', 'timezone' => 'UTC', 'locale' => 'en_IN', 'theme_color' => '#000000', 'header_cta_label' => 'X',
            'header_cta_url' => 'javascript:alert(1)', 'copyright' => 'c', 'org_type' => 'Preschool',
            'enquiry_centres' => 'a', 'enquiry_programmes' => 'b', 'careers_positions' => 'c'])->assertSessionHasErrors('header_cta_url');
    }

    public function test_redirect_management(): void
    {
        $this->admin();
        $this->post('/admin/redirects', ['from_path' => 'old-fees/', 'to_url' => '/admissions', 'status_code' => 301, 'is_enabled' => 1])->assertRedirect('/admin/redirects');
        $this->get('/old-fees')->assertStatus(301)->assertRedirect('http://localhost/admissions');
        $this->post('/admin/redirects', ['from_path' => '/loop', 'to_url' => '/loop', 'status_code' => 301])->assertSessionHasErrors('to_url');
        $this->post('/admin/redirects', ['from_path' => '/x', 'to_url' => 'javascript:alert(1)', 'status_code' => 301])->assertSessionHasErrors('to_url');
        $this->post('/admin/redirects', ['from_path' => '/admin/pages', 'to_url' => '/', 'status_code' => 301])->assertSessionHasErrors('from_path');
        $r = Redirect::where('from_path', '/old-fees')->first();
        $this->delete("/admin/redirects/{$r->id}");
        $this->get('/old-fees')->assertNotFound();
    }

    public function test_enquiry_form_is_validated_and_stored(): void
    {
        Mail::fake();
        \App\Support\Settings::set(['notify_email' => 'office@example.test']);
        $this->postJson('/forms/enquiry', ['name' => '', 'phone' => 'abc'])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone', 'centre', 'programme', 'consent']);
        $this->postJson('/forms/enquiry', [
            'name' => 'Priya', 'phone' => '+91 98765 43210', 'email' => 'p@example.test', 'centre' => 'Centre 1',
            'programme' => 'Nursery', 'type' => 'visit', 'message' => 'Hello', 'consent' => '1',
        ])->assertOk()->assertJson(['message' => 'Thank you! Our team will be in touch soon.']);
        $s = FormSubmission::firstOrFail();
        $this->assertSame('Priya', $s->name);
        $this->assertSame('visit', $s->data['type']);
        Mail::assertSent(\App\Mail\NewSubmission::class, fn ($m) => $m->hasTo('office@example.test'));
        // non-JS submission returns to the form with a status message
        $this->from('/contact')->post('/forms/enquiry', ['name' => 'A', 'phone' => '+91 98765 43210', 'centre' => 'Centre 1', 'programme' => 'Nursery', 'type' => 'general', 'consent' => '1', '_anchor' => 'ct-form'])
            ->assertRedirect('http://localhost/contact#ct-form')->assertSessionHas('form_status.enquiry');
    }

    public function test_honeypot_submissions_are_discarded(): void
    {
        $this->postJson('/forms/enquiry', ['name' => 'Bot', 'phone' => '+1 5555555', 'centre' => 'Centre 1', 'programme' => 'Nursery', 'type' => 'general', 'consent' => '1', 'website' => 'http://spam'])->assertOk();
        $this->assertSame(0, FormSubmission::count());
    }

    public function test_forms_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/forms/enquiry', []);
        }
        $this->postJson('/forms/enquiry', [])->assertStatus(429);
    }

    public function test_careers_form_stores_private_cv(): void
    {
        Storage::fake('local');
        $this->post('/forms/careers', [
            'name' => 'Asha', 'phone' => '+91 98765 43210', 'email' => 'a@example.test', 'position' => '[Position 1]',
            'qualification' => 'B.Ed', 'consent' => '1', 'cv' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();
        $s = FormSubmission::firstOrFail();
        Storage::disk('local')->assertExists($s->attachment_path);
        $this->get("/admin/submissions/{$s->id}/file/cv")->assertRedirect('/admin/login');
        $this->admin('admin')->get("/admin/submissions/{$s->id}/file/cv")->assertOk();
        $this->post('/forms/careers', ['name' => 'X', 'phone' => '+91 98765 43210', 'email' => 'x@example.test', 'position' => '[Position 1]', 'qualification' => 'B', 'consent' => '1',
            'cv' => UploadedFile::fake()->create('cv.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_submissions_admin_and_csv_export(): void
    {
        FormSubmission::create(['form' => 'enquiry', 'name' => '=HYPERLINK("x")', 'phone' => '1', 'data' => ['name' => '=HYPERLINK("x")', 'phone' => '1']]);
        $this->admin('admin');
        $this->get('/admin/submissions')->assertOk()->assertSee('HYPERLINK');
        $csv = $this->get('/admin/submissions/export?form=enquiry')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);   // formula injection neutralised
    }

    public function test_audit_log_is_viewable(): void
    {
        $this->admin();
        $page = Page::where('slug', 'about')->first();
        $this->post("/admin/pages/{$page->id}/duplicate");
        $this->get('/admin/audit')->assertOk()->assertSee('Duplicated “About Us”', false);
    }

    public function test_dashboard_and_seo_overview(): void
    {
        $this->admin();
        $this->get('/admin')->assertOk()->assertSee('Published pages')->assertSee('13');
        $this->get('/admin/seo')->assertOk()->assertSee('Sitemap', false);
    }
}
