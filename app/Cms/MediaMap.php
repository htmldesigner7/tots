<?php

namespace App\Cms;

use App\Models\Media;
use Illuminate\Support\Collection;

/** Preloaded media items (with variants) for one render, avoiding N+1 queries. */
class MediaMap
{
    private Collection $items;

    public function __construct(array $ids = [])
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $this->items = $ids ? Media::with('variants')->whereIn('id', $ids)->get()->keyBy('id') : collect();
    }

    public function get(int $id): ?Media
    {
        return $this->items->get($id);
    }

    /** Collect every media_id found anywhere inside nested section data. */
    public static function idsIn(mixed $data): array
    {
        $ids = [];
        array_walk_recursive($data, function ($v, $k) use (&$ids) {
            if ($k === 'media_id' && $v) {
                $ids[] = (int) $v;
            }
        });

        return $ids;
    }
}
