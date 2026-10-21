@extends('admin.layout')
@section('title', 'My profile')
@section('content')
<div class="head"><h1>My profile</h1></div>
<form method="post" action="{{ route('admin.profile.update') }}" class="card" style="max-width:640px">
  @csrf @method('PUT')
  <label>Name <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120"></label>
  <label>Email <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190"></label>
  <label>New password (optional) <input type="password" name="password" autocomplete="new-password" minlength="12"></label>
  <label>Confirm new password <input type="password" name="password_confirmation" autocomplete="new-password"></label>
  <p class="help">At least 12 characters with upper- and lower-case letters and a number.</p>
  <label>Current password (required to save) <input type="password" name="current_password" required autocomplete="current-password"></label>
  <button class="btn primary">Save profile</button>
</form>
@endsection
