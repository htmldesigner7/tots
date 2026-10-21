<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Support\SiteUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    /** The page being edited (route model, or page_id posted by the preview button). */
    public function targetPage(): ?Page
    {
        $page = $this->route('page');
        if ($page instanceof Page) {
            return $page;
        }

        return $this->filled('page_id') ? Page::find((int) $this->input('page_id')) : null;
    }

    public function authorize(): bool
    {
        $page = $this->targetPage();

        return $page ? $this->user()->can('update', $page) : $this->user()->can('create', Page::class);
    }

    protected function prepareForValidation(): void
    {
        $page = $this->targetPage();
        $slug = $this->input('slug');
        if ($page instanceof Page && $page->isHome()) {
            $slug = '';
        } elseif ($slug === null || trim((string) $slug) === '') {
            $slug = Str::slug((string) $this->input('title'));
        } else {
            $slug = Str::slug((string) $slug);
        }
        $this->merge([
            'slug' => $slug,
            'in_sitemap' => $this->boolean('in_sitemap'),
            'redirect_old_slug' => $this->boolean('redirect_old_slug'),
        ]);
    }

    public function rules(): array
    {
        $page = $this->targetPage();
        $isHome = $page instanceof Page && $page->isHome();
        $reserved = [config('cms.admin_path'), 'storage', 'assets', 'css', 'build', 'forms', 'sitemap', 'robots', 'up', 'index', 'vendor'];

        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => $isHome ? ['present'] : [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn($reserved),
                Rule::unique('pages', 'slug')->ignore($page?->id),
            ],
            'template' => ['required', Rule::in(array_keys(config('cms.page_templates')))],
            'featured_image_id' => ['nullable', 'integer', 'exists:media,id'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'string', 'max:255', 'url:https,http'],
            'robots' => ['required', Rule::in(array_keys(config('cms.robots_options')))],
            'og_title' => ['nullable', 'string', 'max:160'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image_id' => ['nullable', 'integer', 'exists:media,id'],
            'in_sitemap' => ['boolean'],
            'publish_at' => ['nullable', 'date'],
            'sections' => ['required', 'string', 'max:2000000'],
            'action' => ['required', Rule::in(['save', 'publish', 'preview'])],
            'redirect_old_slug' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The URL may only contain lowercase letters, numbers and single dashes.',
            'slug.unique' => 'Another page (possibly in the trash) already uses this URL.',
            'slug.not_in' => 'This URL is reserved by the system.',
        ];
    }

    /** Validated page attributes; SEO fields only for users allowed to edit SEO. */
    public function pageAttributes(?Page $existing): array
    {
        $data = $this->safe()->only(['title', 'slug', 'template', 'featured_image_id', 'meta_title', 'meta_description', 'canonical_url', 'robots', 'og_title', 'og_description', 'og_image_id', 'in_sitemap']);
        $data['title'] = strip_tags($data['title']);
        if (! $this->user()->hasPermission('seo.edit')) {
            foreach (['meta_title', 'meta_description', 'canonical_url', 'robots', 'og_title', 'og_description', 'og_image_id', 'in_sitemap'] as $k) {
                $data[$k] = $existing?->{$k} ?? ($k === 'robots' ? 'index, follow' : ($k === 'in_sitemap' ? true : null));
            }
        }
        foreach (['meta_title', 'meta_description', 'og_title', 'og_description'] as $k) {
            $data[$k] = isset($data[$k]) ? trim(strip_tags($data[$k])) ?: null : null;
        }

        return $data;
    }

    /** Publish time converted from the site time zone to UTC. */
    public function publishAt(): ?string
    {
        if (! $this->filled('publish_at')) {
            return null;
        }

        return \Carbon\Carbon::parse($this->input('publish_at'), \App\Support\Settings::get('timezone'))->utc()->toDateTimeString();
    }
}
