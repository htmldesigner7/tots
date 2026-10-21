@extends('admin.layout')
@section('title', 'Redirects')
@section('content')
<div class="head"><h1>Redirects</h1><div><a class="btn primary" href="{{ route('admin.redirects.create') }}">+ New redirect</a></div></div>
<p class="muted">Send visitors and search engines from an old address to a new one. Redirects are added automatically when you change the URL of a published page.</p>
<form class="filters" method="get"><input type="search" name="search" value="{{ request('search') }}" placeholder="Search" aria-label="Search redirects"><button class="btn sm">Filter</button></form>
<div class="tblwrap"><table class="tbl">
  <thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th>Status</th><th></th></tr></thead>
  <tbody>@forelse($redirects as $r)
    <tr><td><code>{{ $r->from_path }}</code></td><td><code>{{ $r->to_url }}</code></td><td>{{ $r->status_code }}</td><td>{{ $r->hits }}@if($r->last_hit_at)<div class="small muted">{{ $r->last_hit_at->diffForHumans() }}</div>@endif</td>
      <td>{!! $r->is_enabled ? '<span class="badge published">On</span>' : '<span class="badge">Off</span>' !!}</td>
      <td class="actions"><a class="btn sm" href="{{ route('admin.redirects.edit', $r) }}">Edit</a>
        <form method="post" action="{{ route('admin.redirects.destroy', $r) }}" data-confirm="Delete this redirect?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form></td></tr>
  @empty <tr><td colspan="6" class="muted">No redirects.</td></tr>
  @endforelse</tbody>
</table></div>
{{ $redirects->links() }}
@endsection
