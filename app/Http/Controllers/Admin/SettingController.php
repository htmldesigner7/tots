<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaUsageSync;
use App\Support\AuditLogger;
use App\Support\Settings;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        $this->authorize('settings.manage');
        $values = Settings::all();
        $ids = collect(Settings::schema())->filter(fn ($d) => $d['type'] === 'image')->map(fn ($d, $k) => $values[$k]['media_id'] ?? null)->filter()->values();
        $media = Media::with('variants')->whereIn('id', $ids)->get()->mapWithKeys(fn ($m) => [$m->id => ['id' => $m->id, 'thumb' => $m->thumbUrl(), 'url' => $m->url(), 'alt' => $m->alt, 'filename' => $m->filename]]);

        return view('admin.settings', ['groups' => config('cms.settings'), 'values' => $values, 'media' => $media]);
    }

    public function update(Request $request, MediaUsageSync $usage): RedirectResponse
    {
        $this->authorize('settings.manage');
        $schema = Settings::schema();
        $rules = [];
        foreach ($schema as $key => $def) {
            if ($def['type'] === 'boolean') {
                $rules[$key] = ['nullable', 'boolean'];
            } elseif ($def['type'] === 'image') {
                $rules[$key] = ['nullable', 'string', 'max:2000'];
            } else {
                $rules[$key] = explode('|', $def['rules'] ?? 'nullable|string|max:1000');
            }
        }
        $input = $request->all();
        foreach ($schema as $key => $def) {
            if ($def['type'] === 'boolean') {
                $input[$key] = $request->boolean($key);
            }
        }
        $data = Validator::make($input, $rules, [], collect($schema)->map(fn ($d) => strtolower($d['label']))->all())->validate();

        $old = Settings::all();
        $new = [];
        foreach ($schema as $key => $def) {
            $v = $data[$key] ?? null;
            if ($def['type'] === 'image') {
                $v = $this->image($v, $old[$key] ?? $def['default'], $def['label']);
            } elseif ($def['type'] === 'link') {
                if ($v !== null && $v !== '' && ! SiteUrl::isSafe($v)) {
                    throw ValidationException::withMessages([$key => $def['label'].': the link must start with /, #, https://, http://, mailto: or tel:.']);
                }
            } elseif (is_string($v)) {
                $v = trim(strip_tags($v));
            }
            if ($v !== ($old[$key] ?? null)) {
                $new[$key] = $v;
            }
        }
        if ($new) {
            Settings::set($new);
            $usage->settings();
            AuditLogger::log('settings.updated', 'Updated website settings: '.implode(', ', array_keys($new)), null, ['keys' => array_keys($new)]);
        }

        return redirect()->route('admin.settings.edit', ['tab' => $request->input('_tab')])->with('success', $new ? 'Settings saved. Changes are live on every page.' : 'No changes to save.');
    }

    private function image(?string $json, array $current, string $label): array
    {
        $v = json_decode((string) $json, true);
        if (! is_array($v)) {
            return $current;
        }
        $out = ['src' => $current['src'] ?? null, 'media_id' => null, 'alt' => trim(strip_tags((string) ($v['alt'] ?? '')))];
        if (! empty($v['media_id'])) {
            if (! Media::whereKey((int) $v['media_id'])->exists()) {
                throw ValidationException::withMessages(['settings' => $label.': the selected image no longer exists.']);
            }
            $out['media_id'] = (int) $v['media_id'];
        }
        foreach (['w', 'h'] as $d) {
            if (isset($current[$d])) {
                $out[$d] = $current[$d];
            }
        }

        return $out;
    }
}
