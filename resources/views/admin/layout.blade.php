<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @auth
  <meta name="cms-picker" content="{{ route('admin.media.picker') }}">
  <meta name="cms-upload" content="{{ route('admin.media.store') }}">
 <meta name="cms-asset-base" content="{{ rtrim(asset('/'), '/') }}/">
  <meta name="cms-media-edit" content="{{ route('admin.media.show', ['media' => '__ID__']) }}">
  @endauth
  <title>@yield('title', 'Admin') · {{ \App\Support\Settings::get('site_name') }} CMS</title>
  <link rel="icon" href="{{ asset('assets/img/favicon.png') }}" type="image/png">
  @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="adm">
<a class="skip" href="#content">Skip to content</a>
@auth
@php($u = auth()->user())
<div class="adm-shell">
  <aside class="adm-side" id="adm-side">
    <a class="adm-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('assets/img/logo.png') }}" alt="" width="68" height="35"><span>CMS</span></a>
    <nav aria-label="Admin">
      <ul>
        <li><a href="{{ route('admin.dashboard') }}" @class(['on' => request()->routeIs('admin.dashboard')])>Dashboard</a></li>
        @can('pages.view')<li><a href="{{ route('admin.pages.index') }}" @class(['on' => request()->routeIs('admin.pages.*')])>Pages</a></li>@endcan
        @can('viewAny', \App\Models\Media::class)<li><a href="{{ route('admin.media.index') }}" @class(['on' => request()->routeIs('admin.media.*')])>Media library</a></li>@endcan
        @can('menus.manage')<li><a href="{{ route('admin.menus.index') }}" @class(['on' => request()->routeIs('admin.menus.*')])>Menus</a></li>@endcan
        @can('submissions.view')<li><a href="{{ route('admin.submissions.index') }}" @class(['on' => request()->routeIs('admin.submissions.*')])>Enquiries @php($unread = \App\Models\FormSubmission::whereNull('read_at')->count())@if($unread)<b class="pill">{{ $unread }}</b>@endif</a></li>@endcan
        @can('seo.edit')
        <li class="grp">SEO</li>
        <li><a href="{{ route('admin.seo.index') }}" @class(['on' => request()->routeIs('admin.seo.*')])>SEO overview</a></li>
        <li><a href="{{ route('admin.redirects.index') }}" @class(['on' => request()->routeIs('admin.redirects.*')])>Redirects</a></li>
        @endcan
        @if($u->hasPermission('settings.manage') || $u->hasPermission('users.manage') || $u->hasPermission('audit.view'))<li class="grp">Administration</li>@endif
        @can('settings.manage')<li><a href="{{ route('admin.settings.edit') }}" @class(['on' => request()->routeIs('admin.settings.*')])>Settings &amp; global content</a></li>@endcan
        @can('users.manage')
        <li><a href="{{ route('admin.users.index') }}" @class(['on' => request()->routeIs('admin.users.*')])>Users</a></li>
        <li><a href="{{ route('admin.roles.index') }}" @class(['on' => request()->routeIs('admin.roles.*')])>Roles &amp; permissions</a></li>
        @endcan
        @can('audit.view')<li><a href="{{ route('admin.audit.index') }}" @class(['on' => request()->routeIs('admin.audit.*')])>Audit log</a></li>@endcan
      </ul>
    </nav>
  </aside>
  <div class="adm-main">
    <header class="adm-top">
      <button class="adm-burger" type="button" aria-controls="adm-side" aria-expanded="false" data-toggle-side>Menu</button>
      <a class="adm-view" href="{{ url('/') }}" target="_blank" rel="noopener">View website ↗</a>
      <div class="adm-user">
        <a href="{{ route('admin.profile') }}">{{ $u->name }}</a> <span class="muted">· {{ $u->role?->name }}</span>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="linkbtn" type="submit">Sign out</button></form>
      </div>
    </header>
    <main id="content" class="adm-content">
      @include('admin.partials.flash')
      @yield('content')
    </main>
  </div>
</div>
@else
<main id="content" class="adm-auth">
  <div class="adm-auth__box">
    <img src="{{ asset('assets/img/logo.png') }}" alt="{{ \App\Support\Settings::get('site_name') }}" width="136" height="70">
    @include('admin.partials.flash')
    @yield('content')
  </div>
</main>
@endauth
@stack('modals')
</body>
</html>
