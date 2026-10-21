<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaVariant;
use App\Support\PublicCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Upload, validation and optimisation pipeline for the media library.
 *
 *  - The untouched upload is kept on the private "local" disk (recoverable original).
 *  - A web-optimised main file (same format, max 2560px wide, EXIF stripped) and
 *    responsive WebP variants + a thumbnail are written to the "public" disk.
 *  - Crops/rotations are stored as edit instructions and re-applied to the
 *    original, so editing is never destructive.
 */
class MediaService
{
    private const EXT_MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    ];

    private ?ImageManager $imageManager = null;

    public function __construct(private SvgSanitizer $svg)
    {
    }

    /** Created on first use, so listing the library never needs GD; a missing GD gives a clear message instead of a crash. */
    private function images(): ImageManager
    {
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['file' => 'Image processing is not available on this server: the PHP "gd" extension is disabled. Ask the host to enable it (extension=gd), then try again.']);
        }

        return $this->imageManager ??= new ImageManager(new Driver());
    }

    /** Upload a new file into the library. */
    public function store(UploadedFile $file, array $attrs = []): Media
    {
        [$ext, $mime] = $this->validateFile($file);
        if ($ext !== 'svg') {
            $this->images(); // fail early (before anything is written) if GD is unavailable
        }
        $base = $this->baseName($attrs['title'] ?? null ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        $media = new Media([
            'folder_id' => $attrs['folder_id'] ?? null,
            'disk' => 'public',
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'filename' => $base.'.'.$ext,
            'title' => $attrs['title'] ?? Str::headline(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            'alt' => $attrs['alt'] ?? null,
            'mime' => $mime,
            'extension' => $ext,
            'uploaded_by' => auth()->id(),
            'path' => '',
        ]);

        return DB::transaction(function () use ($media, $file, $ext, $base) {
            $this->ingest($media, $file, $ext, $base);
            $media->save();
            $this->generate($media);

            return $media;
        });
    }

    /** Replace the file behind an existing media item, keeping its id (and therefore every placement). */
    public function replace(Media $media, UploadedFile $file): Media
    {
        [$ext, $mime] = $this->validateFile($file);
        if ($ext !== 'svg') {
            $this->images();
        }
        $old = $this->allPaths($media);
        $base = $this->baseName($this->stem($media));

        DB::transaction(function () use ($media, $file, $ext, $mime, $base) {
            $media->fill([
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime' => $mime, 'extension' => $ext, 'edits' => null,
                'filename' => $base.'.'.$ext,
            ]);
            $this->ingest($media, $file, $ext, $base);
            $media->save();
            $media->variants()->delete();
            $this->generate($media);
        });
        $this->deletePaths(array_diff($old, $this->allPaths($media->fresh())));
        PublicCache::flush();

        return $media;
    }

    /**
     * Apply crop/rotate instructions (from the image editor) to the original.
     *
     * @param  array{x:float,y:float,width:float,height:float,rotate?:int}  $edits
     */
    public function edit(Media $media, array $edits, bool $asCopy = false): Media
    {
        if (! $media->isEditable()) {
            throw ValidationException::withMessages(['file' => 'This file type cannot be edited.']);
        }
        $rotate = ((int) ($edits['rotate'] ?? 0)) % 360;
        if ($rotate % 90 !== 0) {
            throw ValidationException::withMessages(['rotate' => 'Rotation must be in 90° steps.']);
        }
        $clean = [
            'rotate' => ($rotate + 360) % 360,
            'x' => max(0, (int) round($edits['x'])),
            'y' => max(0, (int) round($edits['y'])),
            'width' => max(1, (int) round($edits['width'])),
            'height' => max(1, (int) round($edits['height'])),
        ];

        if ($asCopy) {
            $copy = $media->replicate(['path']);
            $copy->filename = $this->baseName($this->stem($media).'-edit').'.'.$media->extension;
            $copy->path = 'media/'.now()->format('Y/m').'/'.$copy->filename;
            $copy->title = trim(($media->title ?: 'Image').' (edited)');
            $newOriginal = dirname($media->original_path).'/'.Str::uuid().'.'.pathinfo($media->original_path, PATHINFO_EXTENSION);
            Storage::disk('local')->copy($media->original_path, $newOriginal);
            $copy->original_path = $newOriginal;
            $copy->edits = $clean;
            $copy->uploaded_by = auth()->id();

            return DB::transaction(function () use ($copy) {
                $copy->save();
                $this->generate($copy);

                return $copy;
            });
        }

        $old = $this->allPaths($media);
        DB::transaction(function () use ($media, $clean) {
            $this->freshPath($media);
            $media->edits = $clean;
            $media->save();
            $media->variants()->delete();
            $this->generate($media);
        });
        $this->deletePaths(array_diff($old, $this->allPaths($media->fresh())));
        PublicCache::flush();

        return $media;
    }

    public function resetEdits(Media $media): Media
    {
        $old = $this->allPaths($media);
        DB::transaction(function () use ($media) {
            $this->freshPath($media);
            $media->edits = null;
            $media->save();
            $media->variants()->delete();
            $this->generate($media);
        });
        $this->deletePaths(array_diff($old, $this->allPaths($media->fresh())));
        PublicCache::flush();

        return $media;
    }

    /** Rename the public files (new descriptive filename). References use the id, so nothing breaks. */
    public function rename(Media $media, string $newName): Media
    {
        $base = $this->baseName($newName);
        $disk = Storage::disk($media->disk);
        DB::transaction(function () use ($media, $base, $disk) {
            $dir = dirname($media->path);
            $newMain = $dir.'/'.$base.'.'.pathinfo($media->path, PATHINFO_EXTENSION);
            if ($newMain !== $media->path && $disk->exists($media->path)) {
                $disk->move($media->path, $newMain);
            }
            $media->path = $newMain;
            $media->filename = basename($newMain);
            foreach ($media->variants as $v) {
                $np = $dir.'/'.$base.'-'.$v->name.'.'.$v->format;
                if ($np !== $v->path && $disk->exists($v->path)) {
                    $disk->move($v->path, $np);
                }
                $v->update(['path' => $np]);
            }
            $media->save();
        });
        PublicCache::flush();

        return $media;
    }

    public function delete(Media $media): void
    {
        $paths = $this->allPaths($media);
        DB::transaction(fn () => $media->delete());
        $this->deletePaths($paths);
        PublicCache::flush();
    }

    /** Import a bundled design asset (public/assets/...) into the library. */
    public function importPublicFile(string $relative, ?string $alt = null, ?int $folderId = null): Media
    {
        $abs = public_path($relative);
        $file = new UploadedFile($abs, basename($abs), null, null, true);

        return $this->store($file, ['alt' => $alt, 'folder_id' => $folderId]);
    }

    // ------------------------------------------------------------------ internals

    /** @return array{0:string,1:string} [extension, mime] */
    public function validateFile(UploadedFile $file): array
    {
        $cfg = config('cms.media');
        $fail = fn (string $m) => throw ValidationException::withMessages(['file' => $m]);

        if (! $file->isValid()) {
            $fail('The upload failed. Please try again.');
        }
        if ($file->getSize() > $cfg['max_upload_kb'] * 1024) {
            $fail('The file is larger than the '.round($cfg['max_upload_kb'] / 1024, 1).' MB limit.');
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $cfg['allowed_extensions'], true)) {
            $fail('Only JPG, PNG, WebP, AVIF, GIF and SVG files can be uploaded.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        if ($ext === 'svg' && in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'], true)) {
            $mime = 'image/svg+xml';
        }
        if (! in_array($mime, $cfg['allowed_mimes'], true) || self::EXT_MIME[$ext] !== $mime) {
            $fail('The file contents do not match an allowed image type.');
        }
        if ($mime !== 'image/svg+xml') {
            $info = @getimagesize($file->getRealPath());
            if (! $info || $info[0] < 1 || $info[1] < 1) {
                $fail('The file is not a valid image.');
            }
            [$w, $h] = $info;
            if ($w > $cfg['max_dimension'] || $h > $cfg['max_dimension'] || $w * $h > $cfg['max_pixels']) {
                $fail("The image is too large ({$w}×{$h}px). Maximum {$cfg['max_dimension']}px per side.");
            }
            if ($w < $cfg['min_dimension'] || $h < $cfg['min_dimension']) {
                $fail("The image is too small ({$w}×{$h}px).");
            }
        }

        return [$ext === 'jpeg' ? 'jpg' : $ext, $mime];
    }

    private function ingest(Media $media, UploadedFile $file, string $ext, string $base): void
    {
        $folder = 'media/'.now()->format('Y/m');
        $media->original_size = $file->getSize();
        if ($ext === 'svg') {
            $clean = $this->svg->sanitize((string) file_get_contents($file->getRealPath()));
            if ($clean === null) {
                throw ValidationException::withMessages(['file' => 'The SVG file could not be validated safely.']);
            }
            $path = $folder.'/'.$base.'.svg';
            Storage::disk('public')->put($path, $clean);
            [$w, $h] = $this->svgSize($clean);
            $media->fill(['path' => $path, 'original_path' => null, 'size' => strlen($clean), 'width' => $w, 'height' => $h,
                'original_width' => $w, 'original_height' => $h, 'optimization' => ['note' => 'SVG sanitised; vector files are not resized.']]);

            return;
        }
        $original = 'media/originals/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;
        Storage::disk('local')->putFileAs(dirname($original), $file, basename($original));
        [$w, $h] = getimagesize($file->getRealPath());
        $media->fill(['original_path' => $original, 'original_width' => $w, 'original_height' => $h]);
        $media->path = $folder.'/'.$base.'.'.$ext; // final name, written by generate()
    }

    private function canDecode(string $mime): bool
    {
        return match ($mime) {
            'image/avif' => function_exists('imagecreatefromavif') && (gd_info()['AVIF Support'] ?? false),
            'image/webp' => (bool) (gd_info()['WebP Support'] ?? false),
            default => true,
        };
    }

    /** (Re)build the main file, responsive variants and thumbnail from the original. */
    public function generate(Media $media): void
    {
        if ($media->isSvg() || ! $media->original_path) {
            return;
        }
        $cfg = config('cms.media');
        $disk = Storage::disk('public');
        $src = Storage::disk('local')->path($media->original_path);
        $dir = dirname($media->path);
        $base = pathinfo($media->path, PATHINFO_FILENAME);

        // GIFs (possibly animated) and formats GD cannot decode are published unchanged.
        if ($media->mime === 'image/gif' || ! $this->canDecode($media->mime)) {
            $disk->put($media->path, file_get_contents($src));
            $media->forceFill([
                'size' => filesize($src), 'width' => $media->original_width, 'height' => $media->original_height,
                'optimization' => ['note' => $media->mime === 'image/gif' ? 'GIF published unchanged to keep animation.' : 'This server cannot decode '.$media->extension.' files; published unchanged.'],
            ])->save();

            return;
        }

        $image = $this->images()->read($src);
        if ($e = $media->edits) {
            if (! empty($e['rotate'])) {
                $image->rotate(-1 * (int) $e['rotate']);
            }
            $cw = min($e['width'], $image->width() - $e['x']);
            $ch = min($e['height'], $image->height() - $e['y']);
            if ($cw > 0 && $ch > 0) {
                $image->crop((int) $cw, (int) $ch, (int) $e['x'], (int) $e['y']);
            }
        }
        $w = $image->width();
        $h = $image->height();

        // main file in the original format, never upscaled
        $main = clone $image;
        if ($w > $cfg['main_max_width']) {
            $main->scaleDown(width: $cfg['main_max_width']);
        }
        $disk->put($media->path, (string) $this->encode($main, $media->extension));
        $mainSize = $disk->size($media->path);

        $variants = [];
        $widths = array_filter($cfg['responsive_widths'], fn ($vw) => $vw < $main->width());
        $widths[] = $main->width();
        foreach (array_unique($widths) as $vw) {
            $v = clone $image;
            $v->scaleDown(width: $vw);
            $path = $dir.'/'.$base.'-w'.$vw.'.webp';
            $disk->put($path, (string) $v->toWebp($cfg['quality']['webp']));
            $variants[] = ['name' => 'w'.$vw, 'format' => 'webp', 'path' => $path, 'width' => $v->width(), 'height' => $v->height(), 'size' => $disk->size($path)];
        }
        [$tw, $th] = $cfg['thumb'];
        $t = clone $image;
        $t->cover($tw, $th);
        $tpath = $dir.'/'.$base.'-thumb.webp';
        $disk->put($tpath, (string) $t->toWebp($cfg['quality']['webp']));
        $variants[] = ['name' => 'thumb', 'format' => 'webp', 'path' => $tpath, 'width' => $t->width(), 'height' => $t->height(), 'size' => $disk->size($tpath)];

        foreach ($variants as $v) {
            $media->variants()->updateOrCreate(['name' => $v['name'], 'format' => $v['format']], $v);
        }
        $largestWebp = collect($variants)->where('name', '!=', 'thumb')->sortByDesc('width')->first();
        $media->forceFill([
            'size' => $mainSize, 'width' => $main->width(), 'height' => $main->height(),
            'optimization' => [
                'original_bytes' => (int) $media->original_size,
                'main_bytes' => $mainSize,
                'webp_bytes' => $largestWebp['size'] ?? null,
                'saved_percent' => $media->original_size ? round(100 - min($mainSize, $largestWebp['size'] ?? PHP_INT_MAX) / $media->original_size * 100, 1) : null,
                'variants' => count($variants),
                'quality' => $cfg['quality'],
            ],
        ])->save();
        $media->unsetRelation('variants');
    }

    private function encode(ImageInterface $img, string $ext): mixed
    {
        $q = config('cms.media.quality');

        return match ($ext) {
            'jpg', 'jpeg' => $img->toJpeg($q['jpeg'], progressive: true),
            'png' => $img->toPng(),
            'webp' => $img->toWebp($q['webp']),
            default => $img->toWebp($q['webp']),
        };
    }

    /** New file name (cache-busting) after an edit; the media id stays the same. */
    private function freshPath(Media $media): void
    {
        $media->filename = $this->baseName($this->stem($media)).'.'.$media->extension;
        $media->path = dirname($media->path).'/'.$media->filename;
    }

    /** Descriptive part of the current filename without the random suffix. */
    private function stem(Media $media): string
    {
        return preg_replace('/-[a-z0-9]{6}$/', '', pathinfo($media->filename, PATHINFO_FILENAME));
    }

    private function baseName(?string $name): string
    {
        $slug = Str::slug(Str::limit((string) $name, 60, '')) ?: 'image';

        return $slug.'-'.Str::lower(Str::random(6));
    }

    private function svgSize(string $svg): array
    {
        $w = $h = null;
        if (preg_match('/viewBox="[\d.\-]+[\s,]+[\d.\-]+[\s,]+([\d.]+)[\s,]+([\d.]+)"/i', $svg, $m)) {
            [$w, $h] = [(int) round($m[1]), (int) round($m[2])];
        }
        if (preg_match('/<svg[^>]*\swidth="([\d.]+)(px)?"/i', $svg, $m)) {
            $w = (int) round($m[1]);
        }
        if (preg_match('/<svg[^>]*\sheight="([\d.]+)(px)?"/i', $svg, $m)) {
            $h = (int) round($m[1]);
        }

        return [$w, $h];
    }

    /** @return string[] public-disk paths of a media item (main + variants) */
    private function allPaths(Media $media): array
    {
        return array_values(array_filter(array_merge([$media->path], $media->variants()->pluck('path')->all(), $media->original_path ? ['local:'.$media->original_path] : [])));
    }

    private function deletePaths(array $paths): void
    {
        foreach ($paths as $p) {
            if (str_starts_with($p, 'local:')) {
                Storage::disk('local')->delete(substr($p, 6));
            } else {
                Storage::disk('public')->delete($p);
            }
        }
    }
}
