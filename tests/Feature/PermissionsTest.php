<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    public function test_guests_are_redirected_from_every_admin_area(): void
    {
        foreach (['/admin', '/admin/pages', '/admin/media', '/admin/menus', '/admin/settings', '/admin/users', '/admin/audit'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
    }

    public function test_content_editor_permissions(): void
    {
        $this->admin('editor');
        $page = Page::where('slug', 'about')->first();
        $this->get('/admin/pages')->assertOk();
        $this->get("/admin/pages/{$page->id}/edit")->assertOk()->assertDontSee('Update &amp; publish', false);
        $this->get('/admin/media')->assertOk();
        foreach (['/admin/settings', '/admin/users', '/admin/menus', '/admin/roles', '/admin/audit', '/admin/redirects', '/admin/seo'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post("/admin/pages/{$page->id}/publish")->assertForbidden();
        $this->post("/admin/pages/{$page->id}/unpublish")->assertForbidden();
        $this->delete("/admin/pages/{$page->id}")->assertForbidden();
    }

    public function test_editor_saving_a_published_page_creates_a_draft_not_a_live_change(): void
    {
        $this->admin('editor');
        $page = Page::where('slug', 'about')->first();
        $sections = $page->sections->map(fn ($s) => ['id' => $s->id, 'type' => $s->type, 'anchor' => $s->anchor, 'is_enabled' => true, 'data' => $s->data])->all();
        $sections[0]['data']['heading_first_line'] = 'Editor change';
        $this->put("/admin/pages/{$page->id}", [
            'title' => 'About Us', 'slug' => 'about', 'template' => 'inner', 'robots' => 'index, follow',
            'sections' => json_encode($sections), 'action' => 'publish',   // not allowed for editors
        ])->assertForbidden();
        $this->put("/admin/pages/{$page->id}", [
            'title' => 'About Us', 'slug' => 'about', 'template' => 'inner', 'robots' => 'noindex, nofollow', 'meta_title' => 'Hacked',
            'sections' => json_encode($sections), 'action' => 'save',
        ])->assertRedirect();
        $this->get('/about')->assertDontSee('Editor change');
        $page->refresh();
        $this->assertTrue($page->hasDraft());
        // SEO fields are ignored for roles without seo.edit
        $this->assertSame('About Ananya Tots | Our Vision, Mission & Philosophy', $page->draft['attrs']['meta_title']);
    }

    public function test_administrator_cannot_manage_users(): void
    {
        $this->admin('admin');
        $this->get('/admin/settings')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
    }

    public function test_super_admin_rules(): void
    {
        $me = $this->user('super-admin');
        $this->actingAs($me);
        $this->delete("/admin/users/{$me->id}")->assertForbidden();   // cannot delete yourself
        $editor = $this->user('editor');
        $this->delete("/admin/users/{$editor->id}")->assertRedirect();
        $this->assertModelMissing($editor);
        // weak passwords are rejected
        $this->post('/admin/users', ['name' => 'N', 'email' => 'n@example.test', 'role_id' => Role::where('slug', 'editor')->value('id'), 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post('/admin/users', ['name' => 'N', 'email' => 'n@example.test', 'role_id' => Role::where('slug', 'editor')->value('id'), 'is_active' => 1, 'password' => 'Long-Enough-Pass1', 'password_confirmation' => 'Long-Enough-Pass1'])
            ->assertRedirect('/admin/users');
        $this->assertDatabaseHas('users', ['email' => 'n@example.test']);
    }

    public function test_only_super_admin_can_change_role_matrix(): void
    {
        $editor = Role::where('slug', 'editor')->first();
        $publish = \App\Models\Permission::where('slug', 'pages.publish')->value('id');
        $this->admin('admin')->put('/admin/roles', ['perm' => [$editor->id => [$publish]]])->assertForbidden();
        $this->admin('super-admin')->put('/admin/roles', ['perm' => [$editor->id => [$publish]]])->assertRedirect();
        $this->assertTrue($editor->fresh()->permissions->contains('slug', 'pages.publish'));
    }

    public function test_admin_cannot_grant_super_admin_role(): void
    {
        $this->admin('admin');
        $super = Role::where('slug', 'super-admin')->first();
        // admins cannot reach user management at all
        $this->post('/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'role_id' => $super->id, 'password' => 'Long-Enough-Pass1', 'password_confirmation' => 'Long-Enough-Pass1'])->assertForbidden();
        $this->assertFalse(User::where('email', 'x@example.test')->exists());
    }
}
