@extends('admin.layout')
@section('title', 'Choose a new password')
@section('content')
<h1>Choose a new password</h1>
<form method="post" action="{{ route('admin.password.update') }}" class="stack">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <label>Email <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"></label>
  <label>New password <input type="password" name="password" required autocomplete="new-password" minlength="12"></label>
  <p class="help">At least 12 characters with upper- and lower-case letters and a number.</p>
  <label>Confirm new password <input type="password" name="password_confirmation" required autocomplete="new-password"></label>
  <button class="btn primary" type="submit">Reset password</button>
</form>
@endsection
