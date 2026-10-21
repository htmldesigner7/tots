@extends('admin.layout')
@section('title', 'Forgot password')
@section('content')
<h1>Reset your password</h1>
<p class="muted">Enter your account email and we will send you a reset link.</p>
<form method="post" action="{{ route('admin.password.email') }}" class="stack">
  @csrf
  <label>Email <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"></label>
  <button class="btn primary" type="submit">Email reset link</button>
  <a class="small" href="{{ route('admin.login') }}">Back to sign in</a>
</form>
@endsection
