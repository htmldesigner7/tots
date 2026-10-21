@extends('admin.layout')
@section('title', 'Media library')
@section('content')
<div class="head"><h1>Media library</h1></div>

@can('create', \App\Models\Media::class)
<section class="card" id="upload">
  <h2>Upload images</h2>
  <form method="post" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" data-upload-form>
    @csrf
    <div class="filters">
      <input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.avif,.gif,.svg" aria-label="Choose files">
      <select name="folder_id" aria-label="Folder"><option value="">No folder</option>@foreach($folders as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach</select>
      <input type="text" name="alt" placeholder="Alt text (optional, applies to all)" maxlength="300" aria-label="Alt text">
      <button class="btn primary">Upload &amp; optimise</button>
    </div>
    <div class="upload-preview" data-upload-preview></div>
    <p class="help">JPG, PNG, WebP, AVIF, GIF or SVG, up to {{ round($maxKb / 1024, 1) }} MB each (max 20 at once). Images are checked, resized into responsive WebP versions and compressed automatically; the original is kept so edits can be undone. SVGs are sanitised.</p>
  </form>
</section>
@endcan

<form class="filters" method="get">
  <input type="search" name="search" value="{{ request('search') }}" placeholder="Search filename, title or alt" aria-label="Search">
  <select name="type" aria-label="File type"><option value="">All types</option>@foreach(['jpeg' => 'JPEG', 'png' => 'PNG', 'webp' => 'WebP', 'avif' => 'AVIF', 'gif' => 'GIF', 'svg' => 'SVG'] as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach</select>
  <select name="folder" aria-label="Folder"><option value="">All folders</option><option value="none" @selected(request('folder') === 'none')>No folder</option>@foreach($folders as $f)<option value="{{ $f->id }}" @selected((string) request('folder') === (string) $f->id)>{{ $f->name }} ({{ $f->media_count }})</option>@endforeach</select>
  <label class="small" style="margin:0">From <input type="date" name="from" value="{{ request('from') }}"></label>
  <label class="small" style="margin:0">To <input type="date" name="to" value="{{ request('to') }}"></label>
  <label class="check small" style="margin:0"><input type="checkbox" name="unused" value="1" @checked(request('unused'))> Unused only</label>
  <button class="btn sm">Filter</button>
  @if(request()->query())<a class="small" href="{{ route('admin.media.index') }}">Clear</a>@endif
</form>

<div class="mgrid">
@forelse($items as $m)
  <a class="mitem" href="{{ route('admin.media.show', $m) }}">
    <img src="{{ $m->thumbUrl() }}" alt="{{ $m->alt }}" loading="lazy">
    <div><strong>{{ $m->filename }}</strong>
      {{ $m->width && $m->height ? $m->width.'×'.$m->height : '' }} · {{ number_format($m->size / 1024) }} KB
      <br><span class="muted">{{ $m->usages_count ? 'Used '.$m->usages_count.'×' : 'Not used' }}@if(!$m->alt && !$m->isSvg()) · <span style="color:#b54708">no alt</span>@endif</span></div>
  </a>
@empty
  <p class="muted">No media found.</p>
@endforelse
</div>
{{ $items->links() }}

@can('create', \App\Models\Media::class)
<section class="card" style="margin-top:24px">
  <h2>Folders</h2>
  <form class="filters" method="post" action="{{ route('admin.media.folders.store') }}">@csrf
    <input type="text" name="name" placeholder="New folder name" required maxlength="120" aria-label="Folder name"><button class="btn sm">Create folder</button>
  </form>
  <ul>@foreach($folders as $f)<li>{{ $f->name }} ({{ $f->media_count }}) <form class="inline" method="post" action="{{ route('admin.media.folders.destroy', $f) }}" data-confirm="Delete the folder “{{ $f->name }}”? Its images stay in the library.">@csrf @method('DELETE')<button class="linkbtn small">delete</button></form></li>@endforeach</ul>
</section>
@endcan
@endsection
