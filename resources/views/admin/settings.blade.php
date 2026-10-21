@extends('admin.layout')
@section('title', 'Settings')
@section('content')
<div class="head"><h1>Settings &amp; global content</h1>
  <form method="post" action="{{ route('admin.cache.clear') }}">@csrf<button class="btn sm ghost">Clear website cache</button></form></div>
<p class="muted">Changes apply to every page. Server secrets (database, mail passwords, app key) are configured in the server’s <code>.env</code> file and are intentionally not editable here.</p>
<form method="post" action="{{ route('admin.settings.update') }}" class="card">
  @csrf @method('PUT')
  <input type="hidden" name="_tab" value="{{ request('tab') }}">
  <div data-tabs>
    <div class="tabs" role="tablist" aria-label="Settings groups">
      @foreach($groups as $group => $fields)
        <button type="button" role="tab" id="tab-{{ Str::slug($group) }}" aria-controls="panel-{{ Str::slug($group) }}" data-key="{{ Str::slug($group) }}">{{ $group }}</button>
      @endforeach
    </div>
    @foreach($groups as $group => $fields)
    <div role="tabpanel" id="panel-{{ Str::slug($group) }}" aria-labelledby="tab-{{ Str::slug($group) }}" hidden>
      @foreach($fields as $key => $def)
        @php($v = old($key, $values[$key] ?? null))
        @switch($def['type'])
          @case('boolean')
            <label class="check"><input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked($v)> {{ $def['label'] }}</label>
            @break
          @case('textarea')
            <label>{{ $def['label'] }}<textarea name="{{ $key }}" rows="{{ $key === 'robots_txt' ? 6 : 3 }}">{{ $v }}</textarea></label>
            @break
          @case('image')
            <label>{{ $def['label'] }}</label>
            @include('admin.partials.image-field', ['name' => $key, 'value' => $values[$key], 'media' => $media])
            <div style="height:14px"></div>
            @break
          @case('select')
            <label>{{ $def['label'] }}<select name="{{ $key }}">@foreach($def['options'] as $ok => $ov)<option value="{{ $ok }}" @selected($v === $ok)>{{ $ov }}</option>@endforeach</select></label>
            @break
          @case('timezone')
            <label>{{ $def['label'] }}<select name="{{ $key }}">@foreach(timezone_identifiers_list() as $z)<option @selected($v === $z)>{{ $z }}</option>@endforeach</select></label>
            @break
          @default
            <label>{{ $def['label'] }}<input type="{{ in_array($def['type'], ['email', 'url', 'tel']) ? $def['type'] : 'text' }}" name="{{ $key }}" value="{{ $v }}" @if($def['type'] === 'link') list="settings-paths" placeholder="/page, #anchor or https://…" @endif></label>
        @endswitch
        @if(!empty($def['help']))<p class="help">{{ $def['help'] }}</p>@endif
      @endforeach
    </div>
    @endforeach
  </div>
  <button class="btn primary">Save settings</button>
</form>
<datalist id="settings-paths">@foreach(\App\Models\Page::orderBy('title')->get() as $p)<option value="{{ $p->path() }}">{{ $p->title }}</option>@endforeach</datalist>
@include('admin.partials.media-modal')
@endsection
