<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_admin_can_log_in_and_out(): void
    {
        $user = $this->user('admin', ['email' => 'a@example.test', 'password' => 'Correct-Horse-9']);
        $this->post('/admin/login', ['email' => 'a@example.test', 'password' => 'Correct-Horse-9'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.login')->exists());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->user('admin', ['email' => 'a@example.test', 'password' => 'Correct-Horse-9']);
        $this->from('/admin/login')->post('/admin/login', ['email' => 'a@example.test', 'password' => 'nope'])
            ->assertRedirect('/admin/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in_and_is_signed_out(): void
    {
        $u = $this->user('admin', ['email' => 'x@example.test', 'password' => 'Correct-Horse-9', 'is_active' => false]);
        $this->post('/admin/login', ['email' => 'x@example.test', 'password' => 'Correct-Horse-9'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($u)->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->user('admin', ['email' => 'r@example.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'r@example.test', 'password' => 'bad']);
        }
        $this->post('/admin/login', ['email' => 'r@example.test', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_csrf_is_enforced_on_admin_forms(): void
    {
        // the testing kernel skips CSRF; verify the middleware is in the web group instead
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web']);
    }

    public function test_password_reset_does_not_reveal_accounts(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $u = $this->user('admin', ['email' => 'known@example.test']);
        $this->post('/admin/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHas('success');
        $this->post('/admin/forgot-password', ['email' => 'known@example.test'])->assertSessionHas('success');
        \Illuminate\Support\Facades\Notification::assertSentTo($u, \Illuminate\Auth\Notifications\ResetPassword::class);
    }
}
