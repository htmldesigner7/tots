@extends('admin.layout')
@section('title', $media->filename)
@php($tz = \App\Support\Settings::get('timezone'))
@section('content')
<div class="head"><div><a class="small" href="{{ route('admin.media.index') }}">← Media library</a><h1>{{ $media->title ?: $media->filename }}</h1></div></div>
<div class="media-layout">
  <div>
    @if($media->isEditable() && auth()->user()->can('update', $media))
    <section class="card">
      <h2>Crop &amp; rotate</h2>
      <p class="help">Drag the box to choose the visible area. Edits are applied to a copy – the original upload is always kept.</p>
      <div class="ratios" role="group" aria-label="Aspect ratio">
        @foreach($presets as $p)
          <button type="button" class="btn sm" data-ratio="{{ $p['ratio'] ?? '' }}" aria-pressed="{{ ($ratio && $p['ratio'] && abs($p['ratio'] - (float) $ratio) < 0.01) || (!$ratio && !$p['ratio']) ? 'true' : 'false' }}">{{ $p['label'] }}</button>
        @endforeach
        @if($ratio)<button type="button" class="btn sm" data-ratio="{{ $ratio }}" aria-pressed="true">Placement ratio ({{ number_format((float) $ratio, 2) }}:1)</button>@endif
      </div>
      <div class="crop-stage"><img id="crop-image" src="{{ route('admin.media.original', $media) }}" alt="Image being edited" data-ratio="{{ $ratio }}"></div>
      <div class="btnrow" style="margin-top:10px">
        <button type="button" class="btn sm" data-rotate="-90">⟲ Rotate left</button>
        <button type="button" class="btn sm" data-rotate="90">⟳ Rotate right</button>
        <button type="button" class="btn sm ghost" data-crop-reset>Reset selection</button>
        <span class="small muted" data-crop-info></span>
      </div>
      <div class="dims" style="margin-top:12px">
        <label>Crop width (px) <input type="number" min="1" data-crop-w></label>
        <label>Crop height (px) <input type="number" min="1" data-crop-h></label>
      </div>
      <form method="post" action="{{ route('admin.media.edit', $media) }}" id="crop-form" class="btnrow" style="margin-top:14px">
        @csrf
        <input type="hidden" name="x"><input type="hidden" name="y"><input type="hidden" name="width"><input type="hidden" name="height"><input type="hidden" name="rotate">
        <button class="btn primary" name="as_copy" value="0">Save crop</button>
        <button class="btn" name="as_copy" value="1">Save as new image</button>
      </form>
      @if($media->edits)
      <form method="post" action="{{ route('admin.media.reset', $media) }}" data-confirm="Remove the crop/rotation and use the original upload again?" style="margin-top:8px">@csrf<button class="btn sm ghost">Restore original</button></form>
      @endif
    </section>
    @else
    <section class="card"><img src="{{ $media->url() }}" alt="{{ $media->alt }}" style="max-width:100%;height:auto">
      @if(!$media->isEditable())<p class="help">{{ $media->isSvg() ? 'SVG files are vector graphics and are not cropped.' : 'This file type cannot be cropped here.' }}</p>@endif
    </section>
    @endif

    <section class="card">
      <h2>Where this image is used</h2>
      @if($media->usages->isEmpty())<p class="muted">Not used anywhere yet. It can be deleted safely.</p>
      @else <ul>@foreach($media->usages as $u)<li>{{ $u->describe() }}@if($u->usable_type === 'page') — <a href="{{ route('admin.pages.edit', $u->usable_id) }}">edit page</a>@endif</li>@endforeach</ul>@endif
    </section>
  </div>

  <aside>
    @if($media->isEditable())
    <section class="card"><h2>Preview of selection</h2><div class="crop-preview"></div>
      @if($media->edits)<h3>Current version</h3><img src="{{ $media->thumbUrl() }}" alt="" style="width:100%;height:auto;border-radius:8px"><p class="help">Currently cropped to {{ $media->width }}×{{ $media->height }} px{{ !empty($media->edits['rotate']) ? ', rotated '.$media->edits['rotate'].'°' : '' }}. New edits start again from the original.</p>@endif
    </section>
    @endif
    <section class="card">
      <h2>Details</h2>
      <form method="post" action="{{ route('admin.media.update', $media) }}">
        @csrf @method('PUT')
        <fieldset @disabled(!auth()->user()->can('update', $media))>
        <label>Alt text <input type="text" name="alt" value="{{ old('alt', $media->alt) }}" maxlength="300"></label>
        <p class="help">Describe what the image shows for people using screen readers. Used wherever a page does not set its own alt text.</p>
        <label>Title <input type="text" name="title" value="{{ old('title', $media->title) }}" maxlength="190"></label>
        <label>Caption <input type="text" name="caption" value="{{ old('caption', $media->caption) }}" maxlength="300"></label>
        <label>File name <input type="text" name="filename" value="{{ preg_replace('/-[a-z0-9]{6}$/', '', pathinfo($media->filename, PATHINFO_FILENAME)) }}" maxlength="80"></label>
        <p class="help">Descriptive file names help SEO. Renaming is safe: pages refer to the image, not the file name.</p>
        <label>Folder <select name="folder_id"><option value="">No folder</option>@foreach($folders as $f)<option value="{{ $f->id }}" @selected($media->folder_id === $f->id)>{{ $f->name }}</option>@endforeach</select></label>
        <button class="btn primary">Save details</button>
        </fieldset>
      </form>
    </section>
    <section class="card">
      <h2>File information</h2>
      <dl class="kv">
        <dt>URL</dt><dd><a href="{{ $media->url() }}" target="_blank" rel="noopener">{{ $media->filename }}</a></dd>
        <dt>Type</dt><dd>{{ $media->mime }}</dd>
        <dt>Displayed size</dt><dd>{{ $media->width }}×{{ $media->height }} px</dd>
        @if($media->original_width)<dt>Original</dt><dd>{{ $media->original_width }}×{{ $media->original_height }} px, {{ number_format($media->original_size / 1024) }} KB</dd>@endif
        <dt>Optimised</dt><dd>{{ number_format($media->size / 1024) }} KB @if(!empty($media->optimization['webp_bytes'])) · WebP {{ number_format($media->optimization['webp_bytes'] / 1024) }} KB @endif @if(isset($media->optimization['saved_percent'])) ({{ $media->optimization['saved_percent'] }}% smaller)@endif</dd>
        @if(!empty($media->optimization['note']))<dt>Note</dt><dd>{{ $media->optimization['note'] }}</dd>@endif
        <dt>Responsive</dt><dd>{{ $media->variants->where('name', '!=', 'thumb')->sortBy('width')->pluck('width')->implode(', ') ?: '—' }}{{ $media->variants->count() ? ' px (WebP)' : '' }}</dd>
        <dt>Uploaded</dt><dd>{{ $media->created_at->timezone($tz)->format('d M Y, H:i') }}{{ $media->uploader ? ' by '.$media->uploader->name : '' }}</dd>
        <dt>Original name</dt><dd>{{ $media->original_name }}</dd>
      </dl>
    </section>
    @can('update', $media)
    <section class="card">
      <h2>Replace file</h2>
      <p class="help">Upload a new version. Every page using this image updates automatically.</p>
      <form method="post" action="{{ route('admin.media.replace', $media) }}" enctype="multipart/form-data" data-upload-form data-confirm="Replace this image everywhere it is used?">
        @csrf
        <input type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.avif,.gif,.svg" aria-label="Replacement file">
        <div class="upload-preview" data-upload-preview></div>
        <button class="btn">Replace</button>
      </form>
    </section>
    <section class="card">
      <h2>Delete</h2>
      @if($media->usages->isNotEmpty())<p class="help">This image is in use and cannot be deleted until it is removed from the places listed.</p>
      @else
      <form method="post" action="{{ route('admin.media.destroy', $media) }}" data-confirm="Delete this image permanently?">@csrf @method('DELETE')<button class="btn danger">Delete image</button></form>
      @endif
    </section>
    @endcan
  </aside>
</div>
@endsection
