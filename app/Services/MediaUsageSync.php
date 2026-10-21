<?php

namespace App\Services;

use App\Cms\MediaMap;
use App\Models\MediaUsage;
use App\Models\Page;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/** Keeps the media_usages table in step with pages and settings (for reference checks). */
class MediaUsageSync
{
    public function page(Page $page): void
    {
        $refs = [];
        foreach ($page->sections()->get() as $i => $section) {
            foreach (MediaMap::idsIn($section->data) as $id) {
                $refs[] = [$id, 'section '.($i + 1).' ('.$section->type.')'];
            }
        }
        if ($page->featured_image_id) {
            $refs[] = [$page->featured_image_id, 'featured image'];
        }
        if ($page->og_image_id) {
            $refs[] = [$page->og_image_id, 'social sharing image'];
        }
        $this->replace('page', $page->id, $refs);
    }

    public function settings(): void
    {
        $refs = [];
        foreach (Settings::schema() as $key => $def) {
            if ($def['type'] === 'image' && ($id = Settings::get($key)['media_id'] ?? null)) {
                $refs[] = [(int) $id, $def['label']];
            }
        }
        $this->replace('setting', 0, $refs);
    }

    private function replace(string $type, int $id, array $refs): void
    {
        DB::transaction(function () use ($type, $id, $refs) {
            MediaUsage::where('usable_type', $type)->where('usable_id', $id)->delete();
            $seen = [];
            foreach ($refs as [$mediaId, $field]) {
                $k = $mediaId.'|'.$field;
                if (isset($seen[$k]) || ! \App\Models\Media::whereKey($mediaId)->exists()) {
                    continue;
                }
                $seen[$k] = true;
                MediaUsage::create(['media_id' => $mediaId, 'usable_type' => $type, 'usable_id' => $id, 'field' => mb_substr($field, 0, 190)]);
            }
        });
    }
}
