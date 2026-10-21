@php($set = \App\Support\Settings::all())
  <!-- ============ HEADER ============ -->
  <header class="site-header">
    <div class="wrap header__inner">
      <a class="logo" href="{{ url('/') }}" aria-label="{{ $set['site_name'] }} — home">
        <img {!! \App\Support\Img::attrs($set['logo'], 136, 70) !!}>
      </a>
      <nav class="main-nav" id="main-nav" aria-label="Main">
        <ul>@foreach(\App\Support\Menus::tree('header') as $item)@php($active = \App\Support\Menus::isActive($item, $currentPath ?? null))@if(count($item['children']))<li class="has-sub{{ $active ? ' is-active' : '' }}"><a href="{{ $item['href'] }}"@if(\App\Support\Menus::isCurrent($item, $currentPath ?? null)) aria-current="page"@endif{!! $item['target_attr'] !!}>{{ $item['label'] }}</a><button class="sub-toggle" type="button" aria-expanded="false" aria-controls="sub-{{ $item['slug'] }}" aria-label="Show {{ $item['label'] }} pages"><svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M2 3.5 5 6.5 8 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button><ul class="sub" id="sub-{{ $item['slug'] }}">@foreach($item['children'] as $child)<li><a href="{{ $child['href'] }}"@if(\App\Support\Menus::isCurrent($child, $currentPath ?? null)) aria-current="page"@endif{!! $child['target_attr'] !!}>{{ $child['label'] }}</a></li>@endforeach</ul></li>@else<li><a href="{{ $item['href'] }}"@if(\App\Support\Menus::isCurrent($item, $currentPath ?? null)) aria-current="page"@endif{!! $item['target_attr'] !!}>{{ $item['label'] }}</a></li>@endif @endforeach</ul>
        <a class="btn btn--nav-mobile" href="{{ \App\Support\SiteUrl::local($set['header_cta_url']) }}">{{ $set['header_cta_label'] }}</a>
      </nav>
      <a class="btn header__cta" href="{{ \App\Support\SiteUrl::local($set['header_cta_url']) }}">{{ $set['header_cta_label'] }}</a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>
