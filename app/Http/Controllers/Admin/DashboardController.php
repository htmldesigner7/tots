<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FormSubmission;
use App\Models\Media;
use App\Models\Page;
use App\Support\AuditLogger;
use App\Support\PublicCache;
use App\Support\Seo;
use App\Support\SystemCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $pages = Page::with('sections')->get();
        $seo = $pages->map(function (Page $p) {
            $checks = Seo::checklist($p, $p->sections);
            $ok = collect($checks)->where('level', '!=', 'info')->where('ok', true)->count();
            $total = collect($checks)->where('level', '!=', 'info')->count();

            return ['page' => $p, 'score' => $total ? (int) round($ok / $total * 100) : 100];
        });

        return view('admin.dashboard', [
            'published' => Page::live()->count(),
            'drafts' => Page::where('status', Page::STATUS_DRAFT)->count(),
            'scheduled' => Page::where('status', Page::STATUS_PUBLISHED)->where('published_at', '>', now())->count(),
            'pendingChanges' => Page::whereNotNull('draft_updated_at')->count(),
            'mediaCount' => Media::count(),
            'mediaSize' => (int) Media::sum('size'),
            'unread' => FormSubmission::whereNull('read_at')->count(),
            'recentPages' => Page::with('editor')->latest('updated_at')->take(6)->get(),
            'recentMedia' => Media::with('variants')->latest()->take(8)->get(),
            'seo' => $seo->sortBy('score')->values(),
            'seoAverage' => (int) round($seo->avg('score') ?? 0),
            'activity' => AuditLog::with('user')->latest('created_at')->take(8)->get(),
            'systemChecks' => auth()->user()->hasPermission('settings.manage') ? SystemCheck::run() : [],
        ]);
    }

    public function clearCache(): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('settings.manage'), 403);
        PublicCache::flush();
        AuditLogger::log('cache.cleared', 'Cleared the website content cache');

        return back()->with('success', 'Website cache cleared.');
    }
}
