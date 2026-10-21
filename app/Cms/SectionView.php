<?php

namespace App\Cms;

use App\Models\Media;
use App\Support\HtmlSanitizer;
use App\Support\SiteUrl;
use Illuminate\Support\Str;

/**
 * Read-only accessor handed to section Blade partials as $s (and to repeater
 * items as $it…). All output helpers return escaped or sanitised strings.
 */
class SectionView
{
    public function __construct(
        private array $data,
        private array $fields,
        private MediaMap $media,
        public string $uid = '',
        public string $anchor = '',
    ) {
    }

    private function field(string $key): ?array
    {
        foreach ($this->fields as $f) {
            if ($f['key'] === $key) {
                return $f;
            }
        }

        return null;
    }

    public function raw(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function filled(string $key): bool
    {
        $v = $this->raw($key);
        if (is_array($v)) {
            return isset($v['label']) ? trim(strip_tags((string) $v['label'])) !== '' : ! empty(array_filter($v));
        }

        return trim(strip_tags((string) $v)) !== '';
    }

    public function bool(string $key): bool
    {
        return (bool) $this->raw($key);
    }

    /** Plain text (escaped by Blade's {{ }}). */
    public function text(string $key): string
    {
        return (string) ($this->raw($key) ?? '');
    }

    /** Inline HTML (em, strong, br, span…) – sanitised. */
    public function html(string $key): string
    {
        return HtmlSanitizer::inline((string) ($this->raw($key) ?? ''));
    }

    /** Rich text (paragraphs, lists) – sanitised. */
    public function rich(string $key): string
    {
        return $this->localiseLinks(HtmlSanitizer::rich((string) ($this->raw($key) ?? '')));
    }

    /** @return string[] sanitised inline HTML per list entry */
    public function list(string $key): array
    {
        return array_map(fn ($v) => HtmlSanitizer::inline((string) $v), array_values((array) ($this->raw($key) ?? [])));
    }

    /** @return SectionView[] */
    public function items(string $key): array
    {
        $sub = $this->field($key)['fields'] ?? [];

        return array_map(
            fn ($item) => new SectionView((array) $item, $sub, $this->media, $this->uid, $this->anchor),
            array_values((array) ($this->raw($key) ?? []))
        );
    }

    public function slug(string $key, string $fallback): string
    {
        $s = Str::slug((string) $this->raw($key));

        return $s !== '' ? $s : $fallback;
    }

    public function url(string $key): string
    {
        $v = (string) ($this->raw($key) ?? '');

        return e(SiteUrl::isSafe($v) ? SiteUrl::local($v) : '#');
    }

    public function linkAttrs(string $key): string
    {
        $v = (array) ($this->raw($key) ?? []);
        $url = (string) ($v['url'] ?? '');
        $href = SiteUrl::isSafe($url) ? SiteUrl::local($url) : '#';
        $out = 'href="'.e($href).'"';
        if (! empty($v['new_tab'])) {
            $out .= ' target="_blank" rel="noopener noreferrer"';
        }

        return $out;
    }

    public function linkLabel(string $key): string
    {
        return HtmlSanitizer::inline((string) (((array) ($this->raw($key) ?? []))['label'] ?? ''));
    }

    /** URL of an image field (media library item or bundled design asset). */
    public function src(string $key): string
    {
        $v = (array) ($this->raw($key) ?? []);
        if (! empty($v['media_id']) && ($m = $this->media->get((int) $v['media_id']))) {
            return e($m->url());
        }

        return e(! empty($v['src']) ? asset($v['src']) : '');
    }

    /**
     * Attributes for an <img>: src, srcset/sizes (library images), alt, width, height.
     * Width/height keep the design's display size so layouts do not shift.
     */
    public function img(string $key): string
    {
        $v = (array) ($this->raw($key) ?? []);
        $decorative = (bool) ($this->field($key)['decorative'] ?? false);
        $alt = $decorative ? '' : (string) ($v['alt'] ?? '');
        $w = isset($v['w']) ? (int) $v['w'] : null;
        $h = isset($v['h']) ? (int) $v['h'] : null;
        $attrs = [];
        $m = ! empty($v['media_id']) ? $this->media->get((int) $v['media_id']) : null;
        if ($m) {
            $attrs['src'] = $m->url();
            if ($alt === '' && ! $decorative) {
                $alt = (string) $m->alt;
            }
            $srcset = $m->srcset('webp');
            if ($srcset !== '') {
                $attrs['srcset'] = $srcset;
                $attrs['sizes'] = $w ? "(max-width: 767px) 100vw, {$w}px" : '100vw';
            }
            if ($m->width && $m->height) {
                if ($w) {
                    $h = (int) round($w * $m->height / $m->width);
                } else {
                    [$w, $h] = [$m->width, $m->height];
                }
            }
        } else {
            $attrs['src'] = ! empty($v['src']) ? asset($v['src']) : '';
        }
        $out = [];
        foreach ($attrs as $k => $val) {
            $out[] = $k.'="'.e($val).'"';
        }
        if ($w && $h) {
            $out[] = 'width="'.$w.'" height="'.$h.'"';
        }
        $out[] = 'alt="'.e($alt).'"';

        return implode(' ', $out);
    }

    private function localiseLinks(string $html): string
    {
        return preg_replace_callback('/href="(\/[^"]*)"/', fn ($m) => 'href="'.e(SiteUrl::local(html_entity_decode($m[1]))).'"', $html);
    }
}
