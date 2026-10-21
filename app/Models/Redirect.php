<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_url', 'status_code', 'is_enabled'];

    protected $casts = ['is_enabled' => 'boolean', 'last_hit_at' => 'datetime'];

    /** Normalise a request path for lookups: leading slash, no trailing slash, no query. */
    public static function normalise(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        $path = '/'.ltrim(rawurldecode($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
