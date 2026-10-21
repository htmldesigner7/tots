<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Seed roles and the approved site content for every test. */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        $this->withoutVite();
    }

    protected function user(string $role = 'super-admin', array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role_id' => Role::where('slug', $role)->value('id'), 'is_active' => true]);
    }

    protected function admin(string $role = 'super-admin'): static
    {
        return $this->actingAs($this->user($role));
    }
}
