<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaVariant extends Model
{
    protected $fillable = ['name', 'format', 'path', 'width', 'height', 'size'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
