@extends('admin.layout')
@section('title', 'Edit menu')
@section('content')
<div class="head"><div><a class="small" href="{{ route('admin.menus.index') }}">← Menus</a><h1>{{ $menu->name }}</h1></div></div>
<form method="post" action="{{ route('admin.menus.update', $menu) }}" id="menu-form" class="card">
  @csrf @method('PUT')
  <input type="hidden" name="items" id="menu-items">
  @if($menu->location !== 'header')
    <label>Column heading <input type="text" name="title" value="{{ old('title', $menu->title) }}" maxlength="120" required></label>
  @endif
  <p class="help">Drag ⠿ to reorder.@if($maxDepth > 1) Drag an item slightly to the right under another item to make it part of a dropdown (one level).@endif Choose a page (links update automatically if its URL changes) or enter a custom URL such as <code>#section</code>, <code>/page#section</code> or <code>https://…</code>. With a page selected, the URL field can hold an <code>#anchor</code> on that page. Links to unpublished pages are hidden automatically.</p>
  <ul class="menu-tree" id="menu-tree"></ul>
  <div class="btnrow" style="margin-top:14px">
    <button type="button" class="btn" data-add-item>+ Add item</button>
    <button class="btn primary">Save menu</button>
  </div>
</form>
<script type="application/json" id="menu-data">{!! json_encode([
    'items' => $tree, 'location' => $menu->location, 'maxDepth' => $maxDepth, 'styles' => config('cms.menu_styles'),
    'pages' => $pages->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'status' => $p->status, 'path' => $p->path()]),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
