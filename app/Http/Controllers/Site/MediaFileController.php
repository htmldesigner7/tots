<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves uploaded media from storage/app/public at /storage/media/....
 *
 * Normally the web server reads these files straight through the public/storage
 * symlink and this controller is never reached. It is the safety net for hosts
 * where that link is missing, broken or not allowed: images still open instead of 403/404.
 * Only files below media/ with an image extension are ever served.
 */
class MediaFileController extends Controller
{
    private const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    ];

    public function show(string $path): BinaryFileResponse
    {
        $path = ltrim($path, '/');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        abort_unless(
            isset(self::TYPES[$ext])
            && str_starts_with($path, 'media/')
            && ! str_contains($path, '..')
            && preg_match('#^[A-Za-z0-9/_.\-]+$#', $path) === 1,
            404
        );

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $headers = [
            'Content-Type' => self::TYPES[$ext],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ];
        if ($ext === 'svg') {
            // uploaded SVGs are sanitised, but never let one run script if opened directly
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($disk->path($path), $headers);
    }
}
