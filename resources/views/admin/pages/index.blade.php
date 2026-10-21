@extends('admin.layout')
@section('title', 'Pages')
@php($tz = \App\Support\Settings::get('timezone'))
@section('content')
<div class="head"><h1>Pages</h1>
  <div>@can('create', \App\Models\Page::class)<a class="btn primary" href="{{ route('admin.pages.create') }}">+ New page</a>@endcan
  @if($trashed)<a class="btn ghost" href="{{ route('admin.pages.trash') }}">Trash ({{ $trashed }})</a>@endif</div>
</div>
<form class="filters" method="get">
  <input type="search" name="search" value="{{ request('search') }}" placeholder="Search title or URL" aria-label="Search pages">
  <select name="status" aria-label="Status"><option value="">All statuses</option>@foreach(['published' => 'Published', 'scheduled' => 'Scheduled', 'draft' => 'Draft', 'changes' => 'With unpublished changes'] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select>
  <button class="btn sm">Filter</button>
</form>
<div class="tblwrap"><table class="tbl">
  <thead><tr><th>Title</th><th>URL</th><th>Status</th><th>Sections</th><th>Last updated</th><th><span class="sr">Actions</span></th></tr></thead>
  <tbody>
  @forelse($pages as $p)
    <tr>
      <td><a href="{{ route('admin.pages.edit', $p) }}"><strong>{{ $p->title }}</strong></a></td>
      <td class="small"><a href="{{ url($p->path()) }}" target="_blank" rel="noopener">{{ $p->path() }}</a></td>
      <td><span class="badge {{ strtolower($p->statusLabel()) }}">{{ $p->statusLabel() }}</span>@if($p->hasDraft())<span class="badge changes" title="Unpublished changes">Changes</span>@endif
        @if($p->isScheduled())<div class="small muted">{{ $p->published_at->timezone($tz)->format('d M Y H:i') }}</div>@endif</td>
      <td>{{ $p->sections_count }}</td>
      <td class="small">{{ $p->updated_at->timezone($tz)->format('d M Y, H:i') }}@if($p->editor)<br><span class="muted">{{ $p->editor->name }}</span>@endif</td>
      <td class="actions">
        <a class="btn sm" href="{{ route('admin.pages.edit', $p) }}">Edit</a>
        @can('create', \App\Models\Page::class)<form method="post" action="{{ route('admin.pages.duplicate', $p) }}">@csrf<button class="btn sm ghost">Duplicate</button></form>@endcan
        @can('delete', $p)<form method="post" action="{{ route('admin.pages.destroy', $p) }}" data-confirm="Move “{{ $p->title }}” to the trash? It will disappear from the website and menus.">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>@endcan
      </td>
    </tr>
  @empty
    <tr><td colspan="6" class="muted">No pages found.</td></tr>
  @endforelse
  </tbody>
</table></div>
{{ $pages->links() }}
@endsection
