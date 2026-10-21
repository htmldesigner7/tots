@extends('admin.layout')
@section('title', $redirect->exists ? 'Edit redirect' : 'New redirect')
@section('content')
<div class="head"><div><a class="small" href="{{ route('admin.redirects.index') }}">← Redirects</a><h1>{{ $redirect->exists ? 'Edit redirect' : 'New redirect' }}</h1></div></div>
<form method="post" action="{{ $redirect->exists ? route('admin.redirects.update', $redirect) : route('admin.redirects.store') }}" class="card" style="max-width:720px">
  @csrf @if($redirect->exists) @method('PUT') @endif
  <label>Old address (path) <input type="text" name="from_path" value="{{ old('from_path', $redirect->from_path) }}" required placeholder="/old-page.html"></label>
  <label>New address <input type="text" name="to_url" value="{{ old('to_url', $redirect->to_url) }}" required placeholder="/new-page or https://…" list="redirect-paths"></label>
  <datalist id="redirect-paths">@foreach(\App\Models\Page::orderBy('title')->get() as $p)<option value="{{ $p->path() }}">{{ $p->title }}</option>@endforeach</datalist>
  <label>Type <select name="status_code">@foreach([301 => '301 – moved permanently (recommended)', 302 => '302 – temporary', 307 => '307 – temporary (keep method)', 308 => '308 – permanent (keep method)'] as $k => $v)<option value="{{ $k }}" @selected((int) old('status_code', $redirect->status_code) === $k)>{{ $v }}</option>@endforeach</select></label>
  <label class="check"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $redirect->is_enabled))> Active</label>
  <button class="btn primary">Save redirect</button>
</form>
@endsection
