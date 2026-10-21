<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Redirect;
use App\Support\Seo;
use Illuminate\View\View;

class SeoController extends Controller
{
    public function index(): View
    {
        $this->authorize('seo.edit');
        $pages = Page::with('sections')->orderByRaw("CASE WHEN slug = '' THEN 0 ELSE 1 END")->orderBy('title')->get();
        $rows = $pages->map(function (Page $p) {
            $checks = collect(Seo::checklist($p, $p->sections))->where('level', '!=', 'info');

            return ['page' => $p, 'checks' => $checks, 'score' => $checks->count() ? (int) round($checks->where('ok', true)->count() / $checks->count() * 100) : 100];
        });

        return view('admin.seo', [
            'rows' => $rows,
            'redirects' => Redirect::count(),
            'robots' => app(\App\Http\Controllers\Site\SeoController::class)->robots()->getContent(),
        ]);
    }
}
