@extends('admin.layout')
@section('title', $page->exists ? 'Edit: '.$page->title : 'New page')
@php
    $tz = \App\Support\Settings::get('timezone');
    $canPublish = auth()->user()->can('publish', $page);
    $canSeo = auth()->user()->hasPermission('seo.edit');
    $isHome = $page->exists && $page->isHome();
    $suffix = \App\Support\Settings::get('seo_title_suffix');
@endphp
@section('content')
<div class="head">
  <div>
    <a class="small" href="{{ route('admin.pages.index') }}">← All pages</a>
    <h1>{{ $page->exists ? $attrs['title'] : 'New page' }}</h1>
    @if($page->exists)
      <span class="badge {{ strtolower($page->statusLabel()) }}">{{ $page->statusLabel() }}</span>
      @if($page->isLive())<a class="small" href="{{ url($page->path()) }}" target="_blank" rel="noopener">View live page ↗</a>@endif
    @endif
  </div>
</div>

@if($fromDraft)
<div class="alert warn">
  You are editing <strong>unpublished changes</strong> saved {{ $page->draft_updated_at?->timezone($tz)->format('d M Y, H:i') }}{{ $page->draftEditor ? ' by '.$page->draftEditor->name : '' }}. The live page still shows the previously published version.
  @if($canPublish)
  <form method="post" action="{{ route('admin.pages.publish', $page) }}" class="inline">@csrf<button class="btn sm primary">Publish these changes</button></form>
  <form method="post" action="{{ route('admin.pages.publish', $page) }}" class="inline" data-confirm="Discard all unpublished changes to this page?">@csrf<input type="hidden" name="discard" value="1"><button class="btn sm ghost">Discard changes</button></form>
  @endif
</div>
@endif

<form method="post" id="page-form" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="editor" novalidate>
  @csrf
  @if($page->exists) @method('PUT') <input type="hidden" name="page_id" value="{{ $page->id }}"> @endif
  <input type="hidden" name="sections" id="sections-input">

  <div class="editor__main">
    <section class="card">
      <label class="big">Page title <span class="req">*</span>
        <input type="text" name="title" value="{{ old('title', $attrs['title']) }}" required maxlength="160" data-title-input>
      </label>
      <p class="help">Shown in the breadcrumb and used as the default SEO title.</p>
      @if($isHome)
        <input type="hidden" name="slug" value="">
        <p><strong>URL:</strong> {{ url('/') }} <span class="muted">(home page)</span></p>
      @else
        <label>URL <span class="slug"><span class="muted">{{ rtrim(url('/'), '/') }}/</span><input type="text" name="slug" value="{{ old('slug', $attrs['slug']) }}" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" data-slug-input @if(!$page->exists) data-auto @endif></span></label>
        @if($page->exists && $page->status === 'published')
          <label class="check"><input type="checkbox" name="redirect_old_slug" value="1" checked> If the URL changes, redirect the old address to the new one (301)</label>
        @endif
      @endif
    </section>

    <section class="card">
      <div class="card__head"><h2>Sections</h2>
        <button type="button" class="btn sm primary" data-add-section>+ Add section</button></div>
      <p class="help">Each section uses an approved design from the website. Click a section to edit its text and images; drag ⠿ to reorder.</p>
      <div id="sections" class="sections" aria-live="polite"></div>
      <button type="button" class="btn ghost wide" data-add-section>+ Add section</button>
    </section>
  </div>

  <aside class="editor__side">
    <section class="card sticky">
      <h2>Publish</h2>
      <p class="small">Status: <strong>{{ $page->exists ? $page->statusLabel() : 'New' }}</strong>
        @if($page->isScheduled())<br>Goes live {{ $page->published_at->timezone($tz)->format('d M Y, H:i') }}@endif</p>
      @if($canPublish)
      <label class="small">Publish date &amp; time <span class="muted">({{ $tz }})</span>
        <input type="datetime-local" name="publish_at" value="{{ old('publish_at', $page->isScheduled() ? $page->published_at->timezone($tz)->format('Y-m-d\TH:i') : '') }}">
      </label>
      <p class="help">Leave empty to publish immediately; a future date schedules the page.</p>
      @endif
      <div class="btnrow">
        <button class="btn" type="submit" name="action" value="save">{{ $page->status === 'published' ? 'Save as draft' : 'Save draft' }}</button>
        <button class="btn ghost" type="submit" name="action" value="preview" formaction="{{ route('admin.pages.preview') }}" formtarget="_blank" data-preview>Preview</button>
        @if($canPublish)<button class="btn primary" type="submit" name="action" value="publish">{{ $page->status === 'published' ? 'Update & publish' : 'Publish' }}</button>@endif
      </div>
      @if(!$canPublish)<p class="help">Your role can save drafts. An administrator will review and publish them.</p>@endif
      @if($page->exists)
      <hr>
      <div class="btnrow">
        @if($canPublish && $page->status === 'published' && !$isHome)
        <button class="btn sm ghost" form="unpublish-form">Unpublish</button>
        @endif
        @can('create', \App\Models\Page::class)<button class="btn sm ghost" form="duplicate-form">Duplicate</button>@endcan
        @can('delete', $page)<button class="btn sm danger" form="delete-form">Delete</button>@endcan
      </div>
      <p class="small muted">Created {{ $page->created_at->timezone($tz)->format('d M Y, H:i') }}{{ $page->creator ? ' by '.$page->creator->name : '' }}<br>Updated {{ $page->updated_at->timezone($tz)->format('d M Y, H:i') }}{{ $page->editor ? ' by '.$page->editor->name : '' }}</p>
      @endif
    </section>

    <section class="card">
      <h2>Layout</h2>
      <label>Template
        <select name="template">@foreach(config('cms.page_templates') as $k => $v)<option value="{{ $k }}" @selected(old('template', $attrs['template']) === $k)>{{ $v }}</option>@endforeach</select>
      </label>
      <label>Featured image <span class="muted small">(used for social sharing when no specific image is set)</span></label>
      @include('admin.partials.image-field', ['name' => 'featured_image_id', 'value' => ['media_id' => $attrs['featured_image_id'] ?? null], 'media' => $media, 'idMode' => true])
    </section>

    <section class="card" id="seo">
      <h2>SEO &amp; sharing</h2>
      @if(!$canSeo)<p class="help">Your role cannot change SEO settings.</p>@endif
      <fieldset @disabled(!$canSeo)>
        <label>SEO title <span class="counter" data-count-for="meta_title" data-min="30" data-max="60"></span>
          <input type="text" name="meta_title" value="{{ old('meta_title', $attrs['meta_title']) }}" maxlength="160" placeholder="{{ $attrs['title'] }}{{ $suffix }}" data-count="meta_title">
        </label>
        <label>Meta description <span class="counter" data-count-for="meta_description" data-min="70" data-max="160"></span>
          <textarea name="meta_description" rows="3" maxlength="320" data-count="meta_description" placeholder="{{ \App\Support\Settings::get('seo_default_description') }}">{{ old('meta_description', $attrs['meta_description']) }}</textarea>
        </label>
        <div class="serp" aria-hidden="true"><div class="serp__t" data-serp-title></div><div class="serp__u">{{ $page->exists ? $page->url() : \App\Support\SiteUrl::to('/') }}</div><div class="serp__d" data-serp-desc></div></div>
        <label>Canonical URL <input type="url" name="canonical_url" value="{{ old('canonical_url', $attrs['canonical_url']) }}" placeholder="Automatic: {{ $page->exists ? $page->url() : 'this page’s URL' }}"></label>
        <label>Search engines
          <select name="robots">@foreach(config('cms.robots_options') as $k => $v)<option value="{{ $k }}" @selected(old('robots', $attrs['robots']) === $k)>{{ $v }}</option>@endforeach</select>
        </label>
        <label class="check"><input type="checkbox" name="in_sitemap" value="1" @checked(old('in_sitemap', $attrs['in_sitemap']))> Include in XML sitemap</label>
        <details>
          <summary>Social sharing (Open Graph)</summary>
          <label>Share title <input type="text" name="og_title" value="{{ old('og_title', $attrs['og_title']) }}" maxlength="160" placeholder="Same as SEO title"></label>
          <label>Share description <textarea name="og_description" rows="2" maxlength="320" placeholder="Same as meta description">{{ old('og_description', $attrs['og_description']) }}</textarea></label>
          <label>Share image <span class="muted small">(1200×630 recommended)</span></label>
          @include('admin.partials.image-field', ['name' => 'og_image_id', 'value' => ['media_id' => $attrs['og_image_id'] ?? null], 'media' => $media, 'idMode' => true])
        </details>
      </fieldset>
      @if($checklist)
      <h3>SEO checklist</h3>
      <ul class="checklist">@foreach($checklist as $c)<li class="{{ $c['ok'] ? 'ok' : $c['level'] }}">{{ $c['text'] }}</li>@endforeach</ul>
      <p class="help">Updated when you save. These checks are guidance, not a guarantee of rankings.</p>
      @endif
    </section>
  </aside>
</form>

@if($page->exists)
<form id="unpublish-form" method="post" action="{{ route('admin.pages.unpublish', $page) }}" data-confirm="Unpublish this page? It will no longer be visible on the website.">@csrf</form>
<form id="duplicate-form" method="post" action="{{ route('admin.pages.duplicate', $page) }}">@csrf</form>
<form id="delete-form" method="post" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="Move this page to the trash? It will disappear from the website and menus.">@csrf @method('DELETE')</form>
@endif

<script type="application/json" id="editor-data">{!! json_encode([
    'sections' => old('sections') ? json_decode(old('sections'), true) : $sections,
    'types' => $types,
    'groups' => $groups,
    'media' => $media,
    'pages' => $pageOptions,
    'presets' => config('cms.media.crop_presets'),
    'defaultsUrl' => route('admin.sections.defaults', ['type' => '__TYPE__']),
    'pickerUrl' => route('admin.media.picker'),
    'uploadUrl' => route('admin.media.store'),
    'mediaEditUrl' => route('admin.media.show', ['media' => '__ID__']),
    'assetBase' => rtrim(asset('/'), '/').'/',
    'suffix' => $suffix,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@include('admin.partials.media-modal')
@endsection
