<?php

namespace App\Cms;

use Illuminate\Support\Facades\File;

/**
 * Registry of design-matched section types. Each type is a Blade partial in
 * resources/views/sections/{key}.blade.php plus a field schema in
 * resources/cms/sections/{key}.json (generated from the approved HTML).
 */
class SectionRegistry
{
    private ?array $types = null;

    public function all(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }
        $types = [];
        foreach (File::glob(resource_path('cms/sections/*.json')) as $file) {
            $def = json_decode(File::get($file), true);
            if (! is_array($def) || empty($def['key'])) {
                continue;
            }
            $def['view'] = 'sections.'.$def['key'];
            $types[$def['key']] = $def;
        }
        uasort($types, fn ($a, $b) => [$a['origin'] === 'index' ? 0 : 1, $a['origin'], $a['label']] <=> [$b['origin'] === 'index' ? 0 : 1, $b['origin'], $b['label']]);

        return $this->types = $types;
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /** Default data for a new section of this type (taken from the page it came from). */
    public function defaults(string $key): array
    {
        $file = resource_path('cms/defaults/'.$key.'.json');
        if (File::exists($file)) {
            return json_decode(File::get($file), true) ?: [];
        }

        return self::emptyFor($this->get($key)['fields'] ?? []);
    }

    public static function emptyFor(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $out[$f['key']] = match ($f['type']) {
                'repeater', 'list' => [],
                'link' => ['label' => '', 'url' => '', 'new_tab' => false],
                'image' => ['src' => null, 'media_id' => null, 'alt' => ''],
                'boolean' => false,
                default => '',
            };
        }

        return $out;
    }

    /** Library listing grouped by the page each section design originates from. */
    public function grouped(): array
    {
        $names = [
            'index' => 'Home', 'about' => 'About', 'programs' => 'Programmes', 'learning' => 'Learning',
            'learning-approach' => 'Learning Approach', 'campus-life' => 'Campus Life', 'life-at-tots' => 'Life at Tots',
            'facilities' => 'Facilities', 'parents' => 'Parents', 'parents-corner' => "Parents' Corner",
            'admissions' => 'Admissions', 'careers' => 'Careers', 'contact' => 'Contact',
        ];
        $groups = [];
        foreach ($this->all() as $key => $def) {
            $groups[$names[$def['origin']] ?? ucfirst($def['origin'])][] = [
                'key' => $key, 'label' => $def['label'], 'css' => $def['css'],
            ];
        }

        return $groups;
    }
}
