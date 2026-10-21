<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Site\FormController;
use App\Http\Controllers\Site\MediaFileController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Uploaded media fallback (used only when public/storage is missing or broken;
| otherwise the web server serves these files directly). No session/cookies.
|--------------------------------------------------------------------------
*/
Route::get('storage/{path}', [MediaFileController::class, 'show'])
    ->where('path', 'media/.+')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->name('media.file');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/
Route::prefix(config('cms.admin_path'))->name('admin.')->middleware('admin.area')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\Auth\LoginController::class, 'show'])->name('login');
        Route::post('login', [Admin\Auth\LoginController::class, 'login'])->middleware('throttle:admin-login')->name('login.attempt');
        Route::get('forgot-password', [Admin\Auth\PasswordResetController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [Admin\Auth\PasswordResetController::class, 'email'])->middleware('throttle:admin-login')->name('password.email');
        Route::get('reset-password/{token}', [Admin\Auth\PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('reset-password', [Admin\Auth\PasswordResetController::class, 'update'])->middleware('throttle:admin-login')->name('password.update');
    });

    Route::middleware(['auth', 'throttle:admin'])->group(function () {
        Route::post('logout', [Admin\Auth\LoginController::class, 'logout'])->name('logout');
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile');
        Route::put('profile', [Admin\ProfileController::class, 'update'])->name('profile.update');

        // Pages
        Route::get('pages/trash', [Admin\PageController::class, 'trash'])->name('pages.trash');
        Route::post('pages/{id}/restore', [Admin\PageController::class, 'restore'])->name('pages.restore');
        Route::delete('pages/{id}/force', [Admin\PageController::class, 'forceDelete'])->name('pages.force');
        Route::post('pages/{page}/duplicate', [Admin\PageController::class, 'duplicate'])->name('pages.duplicate');
        Route::post('pages/{page}/publish', [Admin\PageController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [Admin\PageController::class, 'unpublish'])->name('pages.unpublish');
        Route::match(['post', 'put'], 'pages/preview', [Admin\PageController::class, 'preview'])->name('pages.preview');
        Route::get('pages/{page}/preview', [Admin\PageController::class, 'previewSaved'])->name('pages.preview.saved');
        Route::get('sections/{type}/defaults', [Admin\PageController::class, 'sectionDefaults'])->name('sections.defaults');
        Route::resource('pages', Admin\PageController::class)->except(['show']);

        // Media
        Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
        Route::get('media/picker', [Admin\MediaController::class, 'picker'])->name('media.picker');
        Route::post('media', [Admin\MediaController::class, 'store'])->middleware('throttle:uploads')->name('media.store');
        Route::get('media/{media}', [Admin\MediaController::class, 'show'])->name('media.show');
        Route::get('media/{media}/original', [Admin\MediaController::class, 'original'])->name('media.original');
        Route::put('media/{media}', [Admin\MediaController::class, 'update'])->name('media.update');
        Route::post('media/{media}/replace', [Admin\MediaController::class, 'replace'])->middleware('throttle:uploads')->name('media.replace');
        Route::post('media/{media}/edit', [Admin\MediaController::class, 'edit'])->name('media.edit');
        Route::post('media/{media}/reset', [Admin\MediaController::class, 'reset'])->name('media.reset');
        Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
        Route::post('media-folders', [Admin\MediaController::class, 'storeFolder'])->name('media.folders.store');
        Route::delete('media-folders/{folder}', [Admin\MediaController::class, 'destroyFolder'])->name('media.folders.destroy');

        // Menus
        Route::get('menus', [Admin\MenuController::class, 'index'])->name('menus.index');
        Route::get('menus/{menu}', [Admin\MenuController::class, 'edit'])->name('menus.edit');
        Route::put('menus/{menu}', [Admin\MenuController::class, 'update'])->name('menus.update');

        // Settings & SEO
        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::get('seo', [Admin\SeoController::class, 'index'])->name('seo.index');
        Route::resource('redirects', Admin\RedirectController::class)->except(['show']);

        // Enquiries
        Route::get('submissions', [Admin\SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('submissions/export', [Admin\SubmissionController::class, 'export'])->name('submissions.export');
        Route::get('submissions/{submission}', [Admin\SubmissionController::class, 'show'])->name('submissions.show');
        Route::get('submissions/{submission}/file/{which}', [Admin\SubmissionController::class, 'download'])->name('submissions.download');
        Route::delete('submissions/{submission}', [Admin\SubmissionController::class, 'destroy'])->name('submissions.destroy');

        // Users, roles, audit
        Route::resource('users', Admin\UserController::class)->except(['show']);
        Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::put('roles', [Admin\RoleController::class, 'update'])->name('roles.update');
        Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index');
        Route::post('cache/clear', [Admin\DashboardController::class, 'clearCache'])->name('cache.clear');
    });
});

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::post('forms/enquiry', [FormController::class, 'enquiry'])->middleware('throttle:forms')->name('forms.enquiry');
Route::post('forms/careers', [FormController::class, 'careers'])->middleware('throttle:forms')->name('forms.careers');

Route::get('thank-you', [FormController::class, 'thanks'])->name('thanks');
Route::get('/{slug?}', [PageController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('page');
Route::fallback([PageController::class, 'fallback']);
