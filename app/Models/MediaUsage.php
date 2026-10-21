<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaUsage extends Model
{
    protected $fillable = ['media_id', 'usable_type', 'usable_id', 'field'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** Human readable description of where the media item is used. */
    public function describe(): string
    {
        return match ($this->usable_type) {
            'page' => 'Page: '.(Page::withTrashed()->find($this->usable_id)?->title ?? '#'.$this->usable_id).' → '.$this->field,
            'setting' => 'Setting: '.$this->field,
            default => $this->usable_type.' #'.$this->usable_id.' → '.$this->field,
        };
    }
}
