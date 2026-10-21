<?php

namespace App\Services;

use App\Cms\SectionData;
use App\Cms\SectionRegistry;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Redirect;
use App\Support\AuditLogger;
use App\Support\PublicCache;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Page persistence: validated sections, pending drafts for published pages,
 * publishing, duplication and slug-change redirects. Every multi-table
 * change runs inside a transaction.
 */
class PageService
{
    public function __construct(private SectionRegistry $registry, private MediaUsageSync $usage)
    {
    }

    /**
     * Validate and sanitise the posted sections JSON.
     *
     * @return array<int, array{id:?int,type:string,anchor:?string,is_enabled:bool,data:array}>
     */
    public function cleanSections(mixed $json): array
    {
        $rows = is_string($json) ? json_decode($json, true) : $json;
        if (! is_array($rows)) {
            throw ValidationException::withMessages(['sections' => 'The page sections could not be read. Please reload the editor.']);
        }
        if (count($rows) > 80) {
            throw ValidationException::withMessages(['sections' => 'A page can have at most 80 sections.']);
        }
        $cleaner = new SectionData();
        $out = [];
        foreach (array_values($rows) as $i => $row) {
            $type = $this->registry->get((string) ($row['type'] ?? ''));
            if (! $type) {
                throw ValidationException::withMessages(['sections' => 'Section '.($i + 1).' has an unknown type.']);
            }
            $anchor = trim((string) ($row['anchor'] ?? ''));
            if ($anchor !== '' && ! preg_match('/^[a-z][a-z0-9-]{0,60}$/', $anchor)) {
                $cleaner->errors[] = 'Section '.($i + 1).' ('.$type['label'].'): the anchor may only contain lowercase letters, numbers and dashes.';
                $anchor = '';
            }
            $out[] = [
                'id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null,
                'type' => $type['key'],
                'anchor' => $anchor !== '' ? $anchor : null,
                'is_enabled' => filter_var($row['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'data' => $cleaner->clean($type['fields'], $row['data'] ?? [], 'Section '.($i + 1).' ('.$type['label'].')'),
            ];
        }
        if ($cleaner->errors) {
            throw ValidationException::withMessages(['sections' => array_values(array_unique($cleaner->errors))]);
        }

        return $out;
    }

    /** Create a new (draft or published) page. */
    public function create(array $attrs, array $sections, bool $publish, ?string $publishAt): Page
    {
        return DB::transaction(function () use ($attrs, $sections, $publish, $publishAt) {
            $page = new Page(Arr::only($attrs, Page::CONTENT_FIELDS));
            $page->status = $publish ? Page::STATUS_PUBLISHED : Page::STATUS_DRAFT;
            $page->published_at = $publish ? ($publishAt ?: now()) : null;
            $page->created_by = $page->updated_by = auth()->id();
            $page->save();
            $this->syncSections($page, $sections);
            $this->usage->page($page);
            AuditLogger::log('page.created', 'Created page “'.$page->title.'”'.($publish ? ' and published it' : ' as a draft'), $page);
            PublicCache::flush();

            return $page;
        });
    }

    /**
     * Save edits. Unpublished pages are updated directly; published pages keep
     * their live content and store the edits as a pending draft unless $publish.
     */
    public function save(Page $page, array $attrs, array $sections, bool $publish, ?string $publishAt, bool $redirectOldSlug = true): Page
    {
        return DB::transaction(function () use ($page, $attrs, $sections, $publish, $publishAt, $redirectOldSlug) {
            $attrs = Arr::only($attrs, Page::CONTENT_FIELDS);
            if ($page->status === Page::STATUS_PUBLISHED && ! $publish) {
                $page->draft = ['attrs' => $attrs, 'sections' => $sections];
                $page->draft_updated_at = now();
                $page->draft_updated_by = auth()->id();
                $page->save();
                AuditLogger::log('page.draft_saved', 'Saved unpublished changes to “'.$page->title.'”', $page);

                return $page;
            }
            $oldPath = $page->path();
            $page->fill($attrs);
            $changes = AuditLogger::diff($page);
            if ($publish) {
                $wasLive = $page->isLive();
                $page->status = Page::STATUS_PUBLISHED;
                $page->published_at = $publishAt ?: ($wasLive ? $page->published_at : now());
            }
            $page->draft = null;
            $page->draft_updated_at = null;
            $page->draft_updated_by = null;
            $page->updated_by = auth()->id();
            $page->save();
            $this->syncSections($page, $sections);
            $this->usage->page($page);
            if ($redirectOldSlug && $oldPath !== $page->path() && $page->status === Page::STATUS_PUBLISHED) {
                $this->addSlugRedirect($oldPath, $page->path());
            }
            AuditLogger::log($publish ? 'page.published' : 'page.updated', ($publish ? 'Published' : 'Updated').' page “'.$page->title.'”'.($page->isScheduled() ? ' (scheduled for '.$page->published_at->toDayDateTimeString().' UTC)' : ''), $page, $changes);
            $page->touch();
            PublicCache::flush();

            return $page;
        });
    }

    /** Publish a pending draft (or the page itself). */
    public function publish(Page $page, ?string $publishAt = null): Page
    {
        if ($page->hasDraft()) {
            return $this->save($page, $page->draft['attrs'] ?? [], $page->draft['sections'] ?? [], true, $publishAt);
        }

        return DB::transaction(function () use ($page, $publishAt) {
            $page->status = Page::STATUS_PUBLISHED;
            $page->published_at = $publishAt ?: ($page->published_at && $page->published_at->isPast() ? $page->published_at : now());
            $page->updated_by = auth()->id();
            $page->save();
            AuditLogger::log('page.published', 'Published page “'.$page->title.'”', $page);
            PublicCache::flush();

            return $page;
        });
    }

    public function unpublish(Page $page): Page
    {
        return DB::transaction(function () use ($page) {
            // keep pending edits: they become the page content once it is a draft again
            if ($page->hasDraft()) {
                $draft = $page->draft;
                $page->status = Page::STATUS_DRAFT;
                $page->save();

                return $this->save($page, $draft['attrs'] ?? [], $draft['sections'] ?? [], false, null, false);
            }
            $page->status = Page::STATUS_DRAFT;
            $page->updated_by = auth()->id();
            $page->save();
            AuditLogger::log('page.unpublished', 'Unpublished page “'.$page->title.'”', $page);
            PublicCache::flush();

            return $page;
        });
    }

    public function discardDraft(Page $page): void
    {
        $page->forceFill(['draft' => null, 'draft_updated_at' => null, 'draft_updated_by' => null])->save();
        AuditLogger::log('page.draft_discarded', 'Discarded unpublished changes to “'.$page->title.'”', $page);
    }

    public function duplicate(Page $page): Page
    {
        return DB::transaction(function () use ($page) {
            $source = $this->editable($page);
            $copy = new Page(Arr::only($source['attrs'], Page::CONTENT_FIELDS));
            $copy->title = $source['attrs']['title'].' (copy)';
            $copy->slug = $this->uniqueSlug(($page->slug ?: 'home').'-copy');
            $copy->status = Page::STATUS_DRAFT;
            $copy->published_at = null;
            $copy->meta_title = null;          // avoid duplicate titles / descriptions
            $copy->meta_description = null;
            $copy->canonical_url = null;
            $copy->created_by = $copy->updated_by = auth()->id();
            $copy->save();
            $this->syncSections($copy, array_map(fn ($s) => ['id' => null] + $s, $source['sections']));
            $this->usage->page($copy);
            AuditLogger::log('page.duplicated', 'Duplicated “'.$page->title.'” as “'.$copy->title.'”', $copy, ['source_id' => $page->id]);

            return $copy;
        });
    }

    public function delete(Page $page): void
    {
        DB::transaction(function () use ($page) {
            // free the slug so it can be reused; the original is kept for restoring
            $draft = $page->draft ?? [];
            $page->draft = $draft + ['_deleted_slug' => $page->slug];
            $page->slug = '__deleted-'.$page->id.'-'.$page->slug;
            $page->status = Page::STATUS_DRAFT;
            $page->save();
            $page->delete();
            \App\Models\MediaUsage::where('usable_type', 'page')->where('usable_id', $page->id)->delete();
            \App\Models\MenuItem::where('page_id', $page->id)->update(['is_enabled' => false]);
            AuditLogger::log('page.deleted', 'Moved page “'.$page->title.'” to the trash', $page);
            PublicCache::flush();
        });
    }

    public function restore(Page $page): Page
    {
        return DB::transaction(function () use ($page) {
            $page->restore();
            $draft = $page->draft ?? [];
            $wanted = $draft['_deleted_slug'] ?? null;
            unset($draft['_deleted_slug']);
            $page->draft = $draft ?: null;
            $page->slug = $this->uniqueSlug($wanted ?: 'restored-page', $page->id);
            $page->save();
            $this->usage->page($page);
            AuditLogger::log('page.restored', 'Restored page “'.$page->title.'” as a draft', $page);

            return $page;
        });
    }

    /** Content the editor should show: pending draft if present, else the saved page. */
    public function editable(Page $page): array
    {
        if ($page->hasDraft() && isset($page->draft['sections'])) {
            return ['attrs' => array_merge(Arr::only($page->toArray(), Page::CONTENT_FIELDS), $page->draft['attrs'] ?? []), 'sections' => $page->draft['sections'], 'from_draft' => true];
        }

        return [
            'attrs' => Arr::only($page->toArray(), Page::CONTENT_FIELDS),
            'sections' => $page->sections()->get()->map(fn (PageSection $s) => [
                'id' => $s->id, 'type' => $s->type, 'anchor' => $s->anchor, 'is_enabled' => $s->is_enabled, 'data' => $s->data,
            ])->all(),
            'from_draft' => false,
        ];
    }

    /** Unsaved sections as models (for previews). */
    public function previewSections(array $sections): Collection
    {
        return collect($sections)->values()->map(function ($s, $i) {
            $m = new PageSection(['type' => $s['type'], 'anchor' => $s['anchor'], 'data' => $s['data'], 'sort' => $i, 'is_enabled' => $s['is_enabled']]);
            $m->id = $s['id'] ?? null;

            return $m;
        });
    }

    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $n = 2;
        while (Page::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    private function syncSections(Page $page, array $sections): void
    {
        $existing = $page->sections()->get()->keyBy('id');
        $keep = [];
        foreach (array_values($sections) as $i => $s) {
            $model = ($s['id'] ?? null) && $existing->has($s['id']) ? $existing->get($s['id']) : new PageSection(['page_id' => $page->id]);
            $model->page_id = $page->id;
            $model->fill([
                'type' => $s['type'], 'anchor' => $s['anchor'] ?? null, 'data' => $s['data'],
                'sort' => $i, 'is_enabled' => $s['is_enabled'] ?? true,
            ]);
            $model->save();
            $keep[] = $model->id;
        }
        $page->sections()->whereNotIn('id', $keep ?: [0])->delete();
        $page->unsetRelation('sections');
    }

    private function addSlugRedirect(string $from, string $to): void
    {
        if ($from === '/' || $from === $to) {
            return;
        }
        Redirect::where('from_path', $to)->delete();       // the new address must not redirect away
        Redirect::updateOrCreate(['from_path' => $from], ['to_url' => $to, 'status_code' => 301, 'is_enabled' => true]);
        // keep chains short: anything that pointed at the old path now points at the new one
        Redirect::where('to_url', $from)->update(['to_url' => $to]);
    }
}
