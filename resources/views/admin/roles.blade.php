@extends('admin.layout')
@section('title', 'Roles & permissions')
@php($super = auth()->user()->isSuperAdmin())
@section('content')
<div class="head"><h1>Roles &amp; permissions</h1></div>
<p class="muted">Super Administrators always have every permission. @unless($super) Only a Super Administrator can change this matrix. @endunless</p>
<form method="post" action="{{ route('admin.roles.update') }}">
  @csrf @method('PUT')
  <div class="tblwrap"><table class="tbl perm">
    <thead><tr><th>Permission</th>@foreach($roles as $r)<th>{{ $r->name }}<div class="small muted">{{ $r->users_count }} user(s)</div></th>@endforeach</tr></thead>
    <tbody>
    @foreach($permissions as $group => $perms)
      <tr><td colspan="{{ $roles->count() + 1 }}"><strong>{{ $group }}</strong></td></tr>
      @foreach($perms as $p)
      <tr><td>{{ $p->label }}</td>
        @foreach($roles as $r)
          <td>@if($r->slug === 'super-admin')<span aria-label="Always allowed">✓</span>@else<input type="checkbox" name="perm[{{ $r->id }}][]" value="{{ $p->id }}" aria-label="{{ $r->name }}: {{ $p->label }}" @checked($r->permissions->contains($p)) @disabled(!$super)>@endif</td>
        @endforeach
      </tr>
      @endforeach
    @endforeach
    </tbody>
  </table></div>
  @if($super)<button class="btn primary" style="margin-top:14px">Save permissions</button>@endif
</form>
@endsection
