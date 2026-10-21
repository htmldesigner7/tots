@extends('admin.layout')
@section('title', 'SEO overview')
@section('content')
<div class="head"><h1>SEO overview</h1>
  <div><a class="btn sm" href="{{ route('sitemap') }}" target="_blank" rel="noopener">View sitemap.xml ↗</a><a class="btn sm" href="{{ route('robots') }}" target="_blank" rel="noopener">View robots.txt ↗</a><a class="btn sm" href="{{ route('admin.redirects.index') }}">Redirects ({{ $redirects }})</a></div></div>
<p class="muted">The checklist is guidance to help each page be clear and complete. It does not guarantee search rankings. Default titles, descriptions, the organisation details used in structured data and robots.txt rules are in <a href="{{ route('admin.settings.edit', ['tab' => 'seo-defaults']) }}">Settings → SEO defaults</a>.</p>
<div class="tblwrap"><table class="tbl">
  <thead><tr><th>Page</th><th>Status</th><th>Score</th><th>To improve</th></tr></thead>
  <tbody>@foreach($rows as $r)
    <tr><td><a href="{{ route('admin.pages.edit', $r['page']) }}#seo"><strong>{{ $r['page']->title }}</strong></a><div class="small muted">{{ $r['page']->path() }} · {{ $r['page']->robots }}{{ $r['page']->in_sitemap ? '' : ' · not in sitemap' }}</div></td>
      <td><span class="badge {{ strtolower($r['page']->statusLabel()) }}">{{ $r['page']->statusLabel() }}</span></td>
      <td style="min-width:120px"><div class="meter"><span style="width:{{ $r['score'] }}%"></span></div><span class="small">{{ $r['score'] }}%</span></td>
      <td><ul class="checklist">@foreach($r['checks']->where('ok', false) as $c)<li class="{{ $c['level'] }}">{{ $c['text'] }}</li>@endforeach</ul></td></tr>
  @endforeach</tbody>
</table></div>
<section class="card" style="margin-top:18px"><h2>Current robots.txt</h2><pre>{{ $robots }}</pre></section>
@endsection
