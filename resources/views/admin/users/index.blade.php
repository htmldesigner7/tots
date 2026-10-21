@extends('admin.layout')
@section('title', 'Users')
@php($tz = \App\Support\Settings::get('timezone'))
@section('content')
<div class="head"><h1>Users</h1><div><a class="btn primary" href="{{ route('admin.users.create') }}">+ New user</a></div></div>
<div class="tblwrap"><table class="tbl">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
  <tbody>@foreach($users as $u)
    <tr><td>{{ $u->name }}@if($u->is(auth()->user())) <span class="muted small">(you)</span>@endif</td><td>{{ $u->email }}</td><td>{{ $u->role?->name ?? '—' }}</td>
      <td>{!! $u->is_active ? '<span class="badge published">Active</span>' : '<span class="badge">Disabled</span>' !!}</td>
      <td class="small">{{ $u->last_login_at?->timezone($tz)->format('d M Y, H:i') ?? 'Never' }}</td>
      <td class="actions">
        @can('update', $u)<a class="btn sm" href="{{ route('admin.users.edit', $u) }}">Edit</a>@endcan
        @can('delete', $u)<form method="post" action="{{ route('admin.users.destroy', $u) }}" data-confirm="Delete the account of {{ $u->name }}?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>@endcan
      </td></tr>
  @endforeach</tbody>
</table></div>
{{ $users->links() }}
@endsection
