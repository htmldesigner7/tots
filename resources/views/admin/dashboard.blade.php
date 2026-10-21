@extends('admin.layout')
@section('title', 'Dashboard')
@php($tz = \App\Support\Settings::get('timezone'))
@section('content')
<div class="head"><h1>Dashboard</h1></div>

<div class="stats">
  <a class="stat" href="{{ route('admin.pages.index', ['status' => 'published']) }}"><b>{{ $published }}</b><span>Published pages</span></a>
  <a class="stat" href="{{ route('admin.pages.index', ['status' => 'draft']) }}"><b>{{ $drafts }}</b><span>Draft pages</span></a>
  <a class="stat" href="{{ route('admin.pages.index', ['status' => 'changes']) }}"><b>{{ $pendingChanges }}</b><span>Pages with unpublished changes</span></a>
  <a class="stat" href="{{ route('admin.media.index') }}"><b>{{ $mediaCount }}</b><span>Media files ({{ number_format($mediaSize / 1048576, 1) }} MB)</span></a>
  <a class="stat" href="{{ route('admin.seo.index') }}"><b>{{ $seoAverage }}%</b><span>Average SEO checklist</span></a>
  <a class="stat" href="{{ route('admin.submissions.index') }}"><b>{{ $unread }}</b><span>Unread enquiries</span></a>
</div>
@if($scheduled)<p class="muted">{{ $scheduled }} page(s) scheduled to publish automatically.</p>@endif

@if(count($systemChecks))
<section class="card" id="system-check">
  <h2>System check</h2>
  @php($issues = collect($systemChecks)->where('ok', false)->count())
  <p class="small muted">@if($issues){{ $issues }} thing(s) need attention — these are the usual reasons images do not upload or show on a server.@else Everything needed for uploads and images is in place.@endif</p>
  <table class="tbl">
    <tbody>
    @foreach($systemChecks as $c)
      <tr><td style="width:30%"><b>{{ $c['label'] }}</b></td><td style="width:90px"><span class="badge {{ $c['ok'] ? 'published' : 'draft' }}">{{ $c['ok'] ? 'OK' : 'Fix' }}</span></td><td class="small">{{ $c['detail'] }}</td></tr>
    @endforeach
    </tbody>
  </table>
</section>
@endif

<div class="quick">
  @can('create', \App\Models\Page::class)<a class="btn primary" href="{{ route('admin.pages.create') }}">+ New page</a>@endcan
  @can('create', \App\Models\Media::class)<a class="btn" href="{{ route('admin.media.index') }}#upload">Upload images</a>@endcan
  @can('menus.manage')<a class="btn" href="{{ route('admin.menus.index') }}">Edit menus</a>@endcan
  @can('settings.manage')<a class="btn" href="{{ route('admin.settings.edit') }}">Website settings</a>@endcan
</div>

<div class="grid2">
  <section class="card">
    <h2>Recently updated pages</h2>
    <table class="tbl">
      <tbody>
      @foreach($recentPages as $p)
        <tr><td><a href="{{ route('admin.pages.edit', $p) }}">{{ $p->title }}</a><div class="small muted">{{ $p->path() }}</div></td>
          <td><span class="badge {{ strtolower($p->statusLabel()) }}">{{ $p->statusLabel() }}</span>@if($p->hasDraft())<span class="badge changes">Changes</span>@endif</td>
          <td class="small muted">{{ $p->updated_at->timezone($tz)->format('d M, H:i') }}@if($p->editor)<br>{{ $p->editor->name }}@endif</td></tr>
      @endforeach
      </tbody>
    </table>
  </section>
  <section class="card">
    <h2>SEO checklist status</h2>
    <p class="small muted">Guidance only â a complete checklist does not guarantee rankings.</p>
    <table class="tbl">
      <tbody>
      @foreach($seo->take(6) as $row)
        <tr><td><a href="{{ route('admin.pages.edit', $row['page']) }}#seo">{{ $row['page']->title }}</a></td><td style="width:45%"><div class="meter"><span style="width:{{ $row['score'] }}%"></span></div></td><td class="small">{{ $row['score'] }}%</td></tr>
      @endforeach
      </tbody>
    </table>
  </section>
  <section class="card">
    <h2>Recently uploaded images</h2>
    @if($recentMedia->isEmpty())<p class="muted">No images uploaded yet. The site currently uses the approved design images; upload new ones in the media library.</p>@endif
    <div class="thumbs">
      @foreach($recentMedia as $m)<a href="{{ route('admin.media.show', $m) }}" title="{{ $m->filename }}"><img src="{{ $m->thumbUrl() }}" alt="{{ $m->alt }}" loading="lazy" width="100" height="100"></a>@endforeach
    </div>
  </section>
  <section class="card">
    <h2>Recent activity</h2>
    <ul class="activity">
      @forelse($activity as $a)
        <li><span class="small muted">{{ $a->created_at->timezone($tz)->format('d M, H:i') }}</span> {{ $a->user?->name ?? 'System' }} â {{ $a->description }}</li>
      @empty <li class="muted">No activity yet.</li>
      @endforelse
    </ul>
  </section>
</div>
@endsection
