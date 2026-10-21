@extends('admin.layout')
@section('title', 'Menus')
@section('content')
<div class="head"><h1>Menus</h1></div>
<p class="muted">The header navigation (with dropdowns) and the three footer link columns. The footer “Say Hello” column is managed under Settings → Contact.</p>
<div class="tblwrap"><table class="tbl">
  <thead><tr><th>Menu</th><th>Location</th><th>Heading</th><th>Items</th><th></th></tr></thead>
  <tbody>@foreach($menus as $m)
  <tr><td><strong>{{ $m->name }}</strong></td><td>{{ config('cms.menu_locations')[$m->location] ?? $m->location }}</td><td>{{ $m->title ?: '—' }}</td><td>{{ $m->items_count }}</td><td class="actions"><a class="btn sm" href="{{ route('admin.menus.edit', $m) }}">Edit</a></td></tr>
  @endforeach</tbody>
</table></div>
@endsection
