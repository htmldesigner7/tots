<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'folder_id', 'disk', 'path', 'original_path', 'filename', 'original_name', 'title', 'alt', 'caption',
        'mime', 'extension', 'size', 'original_size', 'width', 'height', 'original_width', 'original_height',
        'edits', 'optimization', 'uploaded_by',
    ];

    protected $casts = [
        'edits' => 'array',
        'optimization' => 'array',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function isSvg(): bool
    {
        return $this->mime === 'image/svg+xml';
    }

    public function isEditable(): bool
    {
        return $this->isImage() && ! $this->isSvg() && $this->mime !== 'image/gif' && $this->original_path;
    }

    /** Public URL built from the current request, so it is right on any host or sub-folder (not tied to APP_URL). */
    public static function publicUrl(string $disk, string $path): string
    {
        return $disk === 'public' ? asset('storage/'.ltrim($path, '/')) : Storage::disk($disk)->url($path);
    }

    public function url(): string
    {
        return self::publicUrl($this->disk, $this->path);
    }

    public function thumbUrl(): string
    {
        $thumb = $this->relationLoaded('variants')
            ? $this->variants->firstWhere('name', 'thumb')
            : $this->variants()->where('name', 'thumb')->first();

        return $thumb ? self::publicUrl($this->disk, $thumb->path) : $this->url();
    }

    /** srcset string for one format, ordered by width. */
    public function srcset(string $format): string
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        return $variants->where('format', $format)->where('name', '!=', 'thumb')->sortBy('width')
            ->map(fn ($v) => self::publicUrl($this->disk, $v->path).' '.$v->width.'w')
            ->implode(', ');
    }

    public function totalVariantSize(): int
    {
        return (int) $this->variants()->sum('size');
    }
}
