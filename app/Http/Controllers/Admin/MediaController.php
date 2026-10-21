<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Services\MediaService;
use App\Support\AuditLogger;
use App\Support\PublicCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function __construct(private MediaService $media)
    {
    }

    private function filtered(Request $request): Builder
    {
        $q = Media::query()->with('variants')->withCount('usages');
        if ($s = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('filename', 'like', "%{$s}%")->orWhere('original_name', 'like', "%{$s}%")
                ->orWhere('title', 'like', "%{$s}%")->orWhere('alt', 'like', "%{$s}%"));
        }
        if ($type = $request->query('type')) {
            $q->where('mime', match ($type) {
                'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'avif' => 'image/avif',
                'gif' => 'image/gif', 'svg' => 'image/svg+xml', default => '__none',
            });
        }
        if ($folder = $request->query('folder')) {
            $folder === 'none' ? $q->whereNull('folder_id') : $q->where('folder_id', (int) $folder);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }
        if ($request->query('unused')) {
            $q->doesntHave('usages');
        }

        return $q->latest();
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Media::class);

        return view('admin.media.index', [
            'items' => $this->filtered($request)->paginate(36)->withQueryString(),
            'folders' => MediaFolder::withCount('media')->orderBy('name')->get(),
            'maxKb' => config('cms.media.max_upload_kb'),
        ]);
    }

    /** JSON listing for the media picker in the page editor and settings. */
    public function picker(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Media::class);
        $page = $this->filtered($request)->where('mime', 'like', 'image/%')->paginate(30);

        return response()->json([
            'items' => $page->getCollection()->map(fn (Media $m) => $this->json($m)),
            'next' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'folders' => MediaFolder::orderBy('name')->get(['id', 'name']),
            'canUpload' => $request->user()->can('create', Media::class),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Media::class);
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['required', 'file', 'max:'.config('cms.media.max_upload_kb')],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'alt' => ['nullable', 'string', 'max:300'],
        ], ['files.*.max' => 'Each file must be smaller than '.round(config('cms.media.max_upload_kb') / 1024, 1).' MB.']);

        $created = [];
        $errors = [];
        foreach ($request->file('files') as $file) {
            try {
                $m = $this->media->store($file, ['folder_id' => $request->input('folder_id'), 'alt' => $request->input('alt')]);
                AuditLogger::log('media.uploaded', 'Uploaded '.$m->filename, $m);
                $created[] = $m;
            } catch (ValidationException $e) {
                $errors[] = $file->getClientOriginalName().': '.collect($e->errors())->flatten()->first();
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['items' => collect($created)->map(fn ($m) => $this->json($m->load('variants'))), 'errors' => $errors], $created ? 200 : 422);
        }
        $msg = count($created).' file(s) uploaded and optimised.';

        return back()->with($created ? 'success' : 'error', $created ? $msg : 'No files were uploaded.')->withErrors($errors ? ['files' => $errors] : []);
    }

    public function show(Media $media): View
    {
        $this->authorize('viewAny', Media::class);
        $media->load(['variants', 'usages', 'folder', 'uploader']);

        return view('admin.media.show', [
            'media' => $media,
            'folders' => MediaFolder::orderBy('name')->get(),
            'presets' => config('cms.media.crop_presets'),
            'ratio' => request('ratio'),
        ]);
    }

    /** Stream the private original (for the crop editor). */
    public function original(Media $media): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('update', $media);
        abort_unless($media->original_path && \Storage::disk('local')->exists($media->original_path), 404);

        return \Storage::disk('local')->response($media->original_path, null, [
            'Content-Type' => $media->mime, 'Cache-Control' => 'private, max-age=600', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('update', $media);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:190'],
            'alt' => ['nullable', 'string', 'max:300'],
            'caption' => ['nullable', 'string', 'max:300'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'filename' => ['nullable', 'string', 'max:80'],
        ]);
        $media->fill([
            'title' => strip_tags((string) ($data['title'] ?? '')) ?: null,
            'alt' => trim(strip_tags((string) ($data['alt'] ?? ''))) ?: null,
            'caption' => strip_tags((string) ($data['caption'] ?? '')) ?: null,
            'folder_id' => $data['folder_id'] ?? null,
        ]);
        $changes = AuditLogger::diff($media);
        $media->save();
        $newName = Str::slug((string) ($data['filename'] ?? ''));
        $currentStem = preg_replace('/-[a-z0-9]{6}$/', '', pathinfo($media->filename, PATHINFO_FILENAME));
        if ($newName !== '' && $newName !== $currentStem) {
            $this->media->rename($media, $newName);
            $changes['filename'] = ['from' => $currentStem, 'to' => $newName];
        }
        PublicCache::flush();
        AuditLogger::log('media.updated', 'Updated details of '.$media->filename, $media, $changes);

        return back()->with('success', 'Image details saved.');
    }

    public function replace(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('update', $media);
        $request->validate(['file' => ['required', 'file', 'max:'.config('cms.media.max_upload_kb')]]);
        $this->media->replace($media, $request->file('file'));
        AuditLogger::log('media.replaced', 'Replaced the file of '.$media->filename.' (all placements updated)', $media);

        return back()->with('success', 'File replaced. Every page using this image now shows the new version.');
    }

    public function edit(Request $request, Media $media): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $media);
        $data = $request->validate([
            'x' => ['required', 'numeric', 'min:0'], 'y' => ['required', 'numeric', 'min:0'],
            'width' => ['required', 'numeric', 'min:1'], 'height' => ['required', 'numeric', 'min:1'],
            'rotate' => ['nullable', 'integer', 'in:0,90,180,270,-90,-180,-270'],
            'as_copy' => ['nullable', 'boolean'],
        ]);
        $result = $this->media->edit($media, $data, $request->boolean('as_copy'));
        AuditLogger::log('media.edited', ($request->boolean('as_copy') ? 'Saved an edited copy of ' : 'Cropped/rotated ').$media->filename, $result, ['crop' => $result->edits]);

        return redirect()->route('admin.media.show', $result)->with('success', $request->boolean('as_copy') ? 'Edited copy saved as a new image. The original is unchanged.' : 'Image cropped. The original upload is kept and can be restored at any time.');
    }

    public function reset(Media $media): RedirectResponse
    {
        $this->authorize('update', $media);
        $this->media->resetEdits($media);
        AuditLogger::log('media.reset', 'Restored the original of '.$media->filename, $media);

        return back()->with('success', 'Original image restored.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);
        $uses = $media->usages()->get();
        if ($uses->isNotEmpty()) {
            return back()->with('error', 'This image is still used: '.$uses->map->describe()->take(5)->implode('; ').'. Replace it there first.');
        }
        $name = $media->filename;
        $this->media->delete($media);
        AuditLogger::log('media.deleted', 'Deleted '.$name, null, ['media_id' => $media->id]);

        return redirect()->route('admin.media.index')->with('success', 'Image deleted.');
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $this->authorize('create', Media::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $slug = Str::slug($data['name']) ?: 'folder';
        $base = $slug;
        $i = 2;
        while (MediaFolder::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $f = MediaFolder::create(['name' => strip_tags($data['name']), 'slug' => $slug]);
        AuditLogger::log('media.folder_created', 'Created media folder “'.$f->name.'”', $f);

        return back()->with('success', 'Folder created.');
    }

    public function destroyFolder(MediaFolder $folder): RedirectResponse
    {
        $this->authorize('create', Media::class);
        $count = $folder->media()->count();
        $folder->delete(); // images stay in the library (folder_id becomes empty)
        AuditLogger::log('media.folder_deleted', 'Deleted media folder “'.$folder->name.'”', null, ['images_unfiled' => $count]);

        return redirect()->route('admin.media.index')->with('success', 'Folder deleted. Its '.$count.' image(s) remain in the library.');
    }

    private function json(Media $m): array
    {
        return [
            'id' => $m->id, 'thumb' => $m->thumbUrl(), 'url' => $m->url(), 'alt' => $m->alt, 'title' => $m->title,
            'filename' => $m->filename, 'width' => $m->width, 'height' => $m->height, 'mime' => $m->mime,
            'size' => $m->size, 'edit_url' => route('admin.media.show', $m),
        ];
    }
}
