<?php

namespace App\Models;

use App\Support\SiteUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = ['menu_id', 'parent_id', 'label', 'page_id', 'url', 'target', 'style', 'is_enabled', 'sort'];

    protected $casts = ['is_enabled' => 'boolean'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort');
    }

    /** Resolved href: linked page (plus optional #fragment in url) or the raw URL. */
    public function href(): string
    {
        if ($this->page_id && $this->page) {
            $frag = ($this->url && str_starts_with($this->url, '#')) ? $this->url : '';

            return SiteUrl::local($this->page->path()).$frag;
        }

        return SiteUrl::local($this->url ?: '#');
    }

    /** Path used to mark the active menu entry. */
    public function matchPath(): ?string
    {
        if ($this->page_id && $this->page) {
            return $this->page->path();
        }
        if ($this->url && str_starts_with($this->url, '/')) {
            return strtok($this->url, '#?');
        }

        return null;
    }
}
