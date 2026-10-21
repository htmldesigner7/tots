<?php

namespace App\Cms;

use App\Models\Media;
use App\Support\HtmlSanitizer;
use App\Support\SiteUrl;
use Illuminate\Support\Str;

/**
 * Validates and sanitises section data against a section type's schema.
 * Unknown keys are dropped, HTML is allow-list sanitised, URLs must use a
 * safe scheme, and image fields may only reference existing media items or
 * bundled design assets under public/assets.
 */
class SectionData
{
    public array $errors = [];

    private const MAX_ITEMS = 60;

    public function clean(array $fields, mixed $data, string $path = ''): array
    {
        $data = is_array($data) ? $data : [];
        $out = [];
        foreach ($fields as $f) {
            $key = $f['key'];
            $label = trim($path.' › '.$f['label'], ' ›');
            $v = $data[$key] ?? null;
            $out[$key] = match ($f['type']) {
                'inline' => Str::limit(HtmlSanitizer::inline(is_string($v) ? $v : ''), 3000, ''),
                'richtext' => Str::limit(HtmlSanitizer::rich(is_string($v) ? $v : ''), 30000, ''),
                'text' => Str::limit(trim(strip_tags(is_string($v) ? $v : '')), 500, ''),
                'boolean' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
                'url' => $this->url($v, $label),
                'link' => $this->link($v, $label),
                'list' => $this->list($v, $label),
                'image' => $this->image($v, $label),
                'repeater' => $this->repeater($f, $v, $label),
                default => null,
            };
        }

        return $out;
    }

    private function url(mixed $v, string $label): string
    {
        $v = trim(is_string($v) ? $v : '');
        if (! SiteUrl::isSafe($v) || strlen($v) > 500) {
            $this->errors[] = "{$label}: the link must start with /, #, https://, http://, mailto: or tel:.";

            return '';
        }

        return $v;
    }

    private function link(mixed $v, string $label): array
    {
        $v = is_array($v) ? $v : [];

        return [
            'label' => Str::limit(HtmlSanitizer::inline((string) ($v['label'] ?? '')), 300, ''),
            'url' => $this->url($v['url'] ?? '', $label),
            'new_tab' => filter_var($v['new_tab'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function list(mixed $v, string $label): array
    {
        if (is_string($v)) {
            $v = preg_split('/\R/', $v);
        }
        $v = array_values(array_filter(array_map(fn ($x) => HtmlSanitizer::inline(is_string($x) ? $x : ''), (array) $v), fn ($x) => trim($x) !== ''));
        if (count($v) > 100) {
            $this->errors[] = "{$label}: too many entries (maximum 100).";
            $v = array_slice($v, 0, 100);
        }

        return $v;
    }

    private function image(mixed $v, string $label): array
    {
        $v = is_array($v) ? $v : [];
        $out = ['src' => null, 'media_id' => null, 'alt' => Str::limit(trim(strip_tags((string) ($v['alt'] ?? ''))), 300, '')];
        if (! empty($v['media_id'])) {
            $id = (int) $v['media_id'];
            if (Media::whereKey($id)->exists()) {
                $out['media_id'] = $id;
            } else {
                $this->errors[] = "{$label}: the selected image no longer exists in the media library.";
            }
        }
        $src = is_string($v['src'] ?? null) ? $v['src'] : null;
        if ($src !== null && $src !== '') {
            if (preg_match('#^assets/[A-Za-z0-9_\-/]+\.(png|jpe?g|webp|avif|gif|svg)$#', $src) && ! str_contains($src, '..') && is_file(public_path($src))) {
                $out['src'] = $src;
            } elseif (! $out['media_id']) {
                $this->errors[] = "{$label}: invalid image reference.";
            }
        }
        foreach (['w', 'h'] as $d) {
            if (isset($v[$d]) && is_numeric($v[$d]) && (int) $v[$d] > 0 && (int) $v[$d] < 10000) {
                $out[$d] = (int) $v[$d];
            }
        }

        return $out;
    }

    private function repeater(array $f, mixed $v, string $label): array
    {
        $items = array_values(is_array($v) ? $v : []);
        if (count($items) > self::MAX_ITEMS) {
            $this->errors[] = "{$label}: too many items (maximum ".self::MAX_ITEMS.').';
            $items = array_slice($items, 0, self::MAX_ITEMS);
        }
        $out = [];
        foreach ($items as $i => $item) {
            $out[] = $this->clean($f['fields'] ?? [], $item, $label.' '.($i + 1));
        }

        return $out;
    }
}
