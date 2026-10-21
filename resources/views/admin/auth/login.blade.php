@extends('admin.layout')
@section('title', 'Sign in')
@section('content')
<h1>Sign in</h1>
<form method="post" action="{{ route('admin.login.attempt') }}" class="stack">
  @csrf
  <label>Email <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
  <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
  <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
  <button class="btn primary" type="submit">Sign in</button>
  <a class="small" href="{{ route('admin.password.request') }}">Forgot your password?</a>
</form>
@endsection
