<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title', 'slug', 'template', 'status', 'published_at', 'featured_image_id',
        'meta_title', 'meta_description', 'canonical_url', 'robots', 'og_title', 'og_description',
        'og_image_id', 'in_sitemap',
    ];

    /** Fields that make up the editable content of a page (and of a pending draft). */
    public const CONTENT_FIELDS = [
        'title', 'slug', 'template', 'featured_image_id', 'meta_title', 'meta_description', 'canonical_url',
        'robots', 'og_title', 'og_description', 'og_image_id', 'in_sitemap',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'in_sitemap' => 'boolean',
        'draft' => 'array',
        'draft_updated_at' => 'datetime',
    ];

    protected $attributes = [
        'template' => 'inner',
        'status' => self::STATUS_DRAFT,
        'robots' => 'index, follow',
        'in_sitemap' => true,
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('sort');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Published and not scheduled in the future. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function draftEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'draft_updated_by');
    }

    public function hasDraft(): bool
    {
        return ! empty($this->draft);
    }

    public function isHome(): bool
    {
        return $this->slug === '';
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && (! $this->published_at || $this->published_at->lte(now()));
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at && $this->published_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isScheduled() => 'Scheduled',
            $this->status === self::STATUS_PUBLISHED => 'Published',
            default => 'Draft',
        };
    }

    public function path(): string
    {
        return $this->isHome() ? '/' : '/'.$this->slug;
    }

    public function url(): string
    {
        return \App\Support\SiteUrl::to($this->path());
    }

    public function isIndexable(): bool
    {
        return ! str_contains($this->robots ?? '', 'noindex');
    }
}
