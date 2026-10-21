<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Support\AuditLogger;
use App\Support\PublicCache;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RedirectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('seo.edit');
        $q = Redirect::query();
        if ($s = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('from_path', 'like', "%{$s}%")->orWhere('to_url', 'like', "%{$s}%"));
        }

        return view('admin.redirects.index', ['redirects' => $q->orderBy('from_path')->paginate(30)->withQueryString()]);
    }

    public function create(): View
    {
        $this->authorize('seo.edit');

        return view('admin.redirects.form', ['redirect' => new Redirect(['status_code' => 301, 'is_enabled' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('seo.edit');
        $r = Redirect::create($this->validated($request));
        PublicCache::flush();
        AuditLogger::log('redirect.created', "Created redirect {$r->from_path} → {$r->to_url}", $r);

        return redirect()->route('admin.redirects.index')->with('success', 'Redirect created.');
    }

    public function edit(Redirect $redirect): View
    {
        $this->authorize('seo.edit');

        return view('admin.redirects.form', ['redirect' => $redirect]);
    }

    public function update(Request $request, Redirect $redirect): RedirectResponse
    {
        $this->authorize('seo.edit');
        $redirect->fill($this->validated($request, $redirect));
        $changes = AuditLogger::diff($redirect);
        $redirect->save();
        PublicCache::flush();
        AuditLogger::log('redirect.updated', "Updated redirect {$redirect->from_path}", $redirect, $changes);

        return redirect()->route('admin.redirects.index')->with('success', 'Redirect updated.');
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $this->authorize('seo.edit');
        $redirect->delete();
        PublicCache::flush();
        AuditLogger::log('redirect.deleted', "Deleted redirect {$redirect->from_path} → {$redirect->to_url}");

        return back()->with('success', 'Redirect deleted.');
    }

    private function validated(Request $request, ?Redirect $existing = null): array
    {
        $request->merge(['from_path' => Redirect::normalise((string) $request->input('from_path')), 'is_enabled' => $request->boolean('is_enabled')]);
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:190', 'not_in:/', Rule::unique('redirects', 'from_path')->ignore($existing?->id),
                'not_regex:#^/'.preg_quote(config('cms.admin_path'), '#').'(/|$)#'],
            'to_url' => ['required', 'string', 'max:500'],
            'status_code' => ['required', Rule::in([301, 302, 307, 308])],
            'is_enabled' => ['boolean'],
        ], [
            'from_path.not_in' => 'The home page cannot be redirected.',
            'from_path.not_regex' => 'Admin URLs cannot be redirected.',
        ]);
        $to = trim($data['to_url']);
        if (! SiteUrl::isSafe($to) || str_starts_with($to, '#')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to_url' => 'Enter a path starting with / or a full https:// address.']);
        }
        if (Redirect::normalise($to) === $data['from_path'] && str_starts_with($to, '/')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to_url' => 'A redirect cannot point to itself.']);
        }
        $data['to_url'] = $to;

        return $data;
    }
}
