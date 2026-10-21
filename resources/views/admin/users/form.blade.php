@extends('admin.layout')
@section('title', $user->exists ? 'Edit user' : 'New user')
@php($self = $user->exists && $user->is(auth()->user()))
@section('content')
<div class="head"><div><a class="small" href="{{ route('admin.users.index') }}">← Users</a><h1>{{ $user->exists ? 'Edit '.$user->name : 'New user' }}</h1></div></div>
<form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card" style="max-width:640px">
  @csrf @if($user->exists) @method('PUT') @endif
  <label>Name <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120"></label>
  <label>Email <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" autocomplete="off"></label>
  <label>Role
    <select name="role_id" @disabled($self)>@foreach($roles as $r)<option value="{{ $r->id }}" @selected((int) old('role_id', $user->role_id) === $r->id)>{{ $r->name }} — {{ $r->description }}</option>@endforeach</select>
  </label>
  @if($self)<input type="hidden" name="role_id" value="{{ $user->role_id }}"><p class="help">You cannot change your own role.</p>@endif
  <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) @disabled($self)> Account active (can sign in)</label>
  @if($self)<input type="hidden" name="is_active" value="1">@endif
  <label>{{ $user->exists ? 'New password (leave empty to keep the current one)' : 'Password' }} <input type="password" name="password" autocomplete="new-password" minlength="12" @required(!$user->exists)></label>
  <label>Confirm password <input type="password" name="password_confirmation" autocomplete="new-password"></label>
  <p class="help">At least 12 characters with upper- and lower-case letters and a number.</p>
  <button class="btn primary">Save user</button>
</form>
@endsection
