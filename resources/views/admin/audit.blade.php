@extends('admin.layout')
@section('title', 'Audit log')
@php($tz = \App\Support\Settings::get('timezone'))
@section('content')
<div class="head"><h1>Audit log</h1></div>
<form class="filters" method="get">
  <input type="search" name="search" value="{{ request('search') }}" placeholder="Search descriptions" aria-label="Search">
  <select name="user" aria-label="User"><option value="">All users</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }}</option>@endforeach</select>
  <select name="action" aria-label="Area"><option value="">All areas</option>@foreach($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ ucfirst($a) }}</option>@endforeach</select>
  <button class="btn sm">Filter</button>
</form>
<div class="tblwrap"><table class="tbl">
  <thead><tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
  <tbody>@forelse($logs as $l)
    <tr><td class="small">{{ $l->created_at->timezone($tz)->format('d M Y, H:i:s') }}</td><td>{{ $l->user?->name ?? '—' }}</td><td><code>{{ $l->action }}</code></td>
      <td>{{ $l->description }}@if($l->properties)<details><summary class="small">Details</summary><pre class="small" style="white-space:pre-wrap">{{ json_encode($l->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif</td>
      <td class="small">{{ $l->ip }}</td></tr>
  @empty <tr><td colspan="5" class="muted">No entries.</td></tr>
  @endforelse</tbody>
</table></div>
{{ $logs->links() }}
@endsection
