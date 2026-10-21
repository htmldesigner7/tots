<?php

namespace App\Console\Commands;

use App\Cms\SectionRegistry;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Page;
use App\Services\MediaService;
use App\Services\MediaUsageSync;
use App\Support\PublicCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Optional: copy the content photos of the approved design (public/assets/img)
 * into the media library so administrators can crop, replace and reuse them.
 * Decorative design pieces (icons, strokes, doodles) stay as design assets.
 */
class ImportImages extends Command
{
    protected $signature = 'cms:import-images {--dry-run : Only list what would be imported}';

    protected $description = 'Import the website’s content photos into the media library (with optimised responsive versions)';

    public function handle(SectionRegistry $registry, MediaService $media, MediaUsageSync $usage): int
    {
        $folder = MediaFolder::firstOrCreate(['slug' => 'website-photos'], ['name' => 'Website photos']);
        $imported = [];   // src => media id (reuse when the same photo appears twice)
        $count = 0;
        foreach (Page::with('sections')->get() as $page) {
            $changed = false;
            foreach ($page->sections as $section) {
                $type = $registry->get($section->type);
                if (! $type) {
                    continue;
                }
                $data = $section->data;
                $this->walk($type['fields'], $data, function (array &$v) use (&$imported, &$count, $media, $folder) {
                    if (! empty($v['media_id']) || empty($v['src']) || ! is_file(public_path($v['src']))) {
                        return false;
                    }
                    if (! isset($imported[$v['src']])) {
                        if ($this->option('dry-run')) {
                            $this->line('would import '.$v['src']);
                            $imported[$v['src']] = 0;

                            return false;
                        }
                        $m = $media->importPublicFile($v['src'], $v['alt'] ?: null, $folder->id);
                        $imported[$v['src']] = $m->id;
                        $count++;
                        $this->line("imported {$v['src']} → #{$m->id}");
                    }
                    $v['media_id'] = $imported[$v['src']] ?: null;

                    return (bool) $v['media_id'];
                });
                if ($data !== $section->data && ! $this->option('dry-run')) {
                    $section->data = $data;
                    $section->save();
                    $changed = true;
                }
            }
            if ($changed) {
                $usage->page($page);
            }
        }
        PublicCache::flush();
        $this->info($this->option('dry-run') ? count($imported).' image(s) would be imported.' : "Imported {$count} image(s).");

        return self::SUCCESS;
    }

    /** Visit every non-decorative image field, recursing into repeaters. */
    private function walk(array $fields, array &$data, callable $fn): void
    {
        foreach ($fields as $f) {
            if (! array_key_exists($f['key'], $data)) {
                continue;
            }
            if ($f['type'] === 'image' && empty($f['decorative']) && is_array($data[$f['key']])) {
                $fn($data[$f['key']]);
            } elseif ($f['type'] === 'repeater' && is_array($data[$f['key']])) {
                foreach ($data[$f['key']] as &$item) {
                    $this->walk($f['fields'], $item, $fn);
                }
                unset($item);
            }
        }
    }
}
