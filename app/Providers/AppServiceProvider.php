<?php

namespace App\Providers;

use App\Cms\PageRenderer;
use App\Cms\SectionRegistry;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Policies\MediaPolicy;
use App\Policies\PagePolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SectionRegistry::class);
        $this->app->singleton(PageRenderer::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Permission abilities (checked with @can / $this->authorize / can: middleware)
        foreach (array_keys(config('cms.permissions')) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('admin-login', function (Request $request) {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return [Limit::perMinute(5)->by($key), Limit::perMinute(20)->by($request->ip())];
        });
        RateLimiter::for('admin', fn (Request $r) => Limit::perMinute(600)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('forms', fn (Request $r) => [Limit::perMinute(5)->by($r->ip()), Limit::perDay(50)->by($r->ip())]);

        ResetPassword::createUrlUsing(fn ($user, string $token) => route('admin.password.reset', ['token' => $token, 'email' => $user->email]));

        Paginator::defaultView('admin.partials.pagination');
    }
}
