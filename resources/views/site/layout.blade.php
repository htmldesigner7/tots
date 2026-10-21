@php
    /** @var \App\Support\Seo $seo */
    $set = \App\Support\Settings::all();
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $set['locale'] === 'en_IN' ? 'en' : $set['locale']) }}" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $seo->title }}</title>
  <meta name="description" content="{{ $seo->description }}">
  <meta name="robots" content="{{ $seo->robots }}">
  @if($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif

  <meta name="theme-color" content="{{ $set['theme_color'] }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{{ $set['site_name'] }}">
  <meta property="og:title" content="{{ $seo->ogTitle }}">
  <meta property="og:description" content="{{ $seo->ogDescription }}">
  @if($seo->canonical)<meta property="og:url" content="{{ $seo->canonical }}">@endif

  @if($seo->ogImage)<meta property="og:image" content="{{ $seo->ogImage }}">@endif

  <meta property="og:locale" content="{{ $set['locale'] }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $seo->ogTitle }}">
  <meta name="twitter:description" content="{{ $seo->ogDescription }}">
  @if($seo->ogImage)<meta name="twitter:image" content="{{ $seo->ogImage }}">@endif

  <link rel="icon" href="{{ \App\Support\Img::url($set['favicon']) }}" type="image/png">
  <link rel="icon" href="{{ url('favicon.ico') }}" sizes="48x48">
  <link rel="apple-touch-icon" href="{{ asset('assets/img/apple-touch-icon.png') }}">
  <link rel="preload" href="{{ asset('assets/fonts/league-gothic-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="{{ asset('assets/fonts/quicksand-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="{{ asset('assets/fonts/dynapuff-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
  @if(!empty($preloadImage))<link rel="preload" href="{{ $preloadImage }}" as="image" fetchpriority="high">@endif

  <script nonce="{{ $nonce }}">document.documentElement.classList.replace('no-js','js')</script>
  <link rel="stylesheet" href="{{ \App\Support\Asset::v('style.css') }}">
  @if(in_array('inner', $css ?? [], true))
  <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/inner.css') }}">
  <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/flow.css') }}">
  @endif
  @foreach($seo->jsonLd as $ld)
  <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  @endforeach
  <style>.cms-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}.cms-err{display:block;margin-top:6px;color:#b42318;font-size:14px;line-height:1.4}.cms-announce{background:#673893;color:#fff;text-align:center;font-size:15px;line-height:1.4;padding:9px 16px}.cms-announce p{margin:0}.cms-announce a{color:#fff;font-weight:700;text-decoration:underline}.cms-preview-bar{position:sticky;top:0;z-index:9999;background:#1d2939;color:#fff;font:600 14px/1.4 system-ui,sans-serif;padding:10px 16px;text-align:center}.cms-preview-bar a{color:#fde68a;margin-left:8px}</style>
</head>
<body @if(!empty($bodyClass))class="{{ $bodyClass }}" @endif id="top">
@if(!empty($isPreview))<div class="cms-preview-bar" role="status">Preview – this page is not published in this form yet. <a href="{{ $previewBack ?? url('/') }}">Back to editor</a></div>@endif
<a class="skip-link" href="#main">Skip to content</a>
<div class="site">
  @if($set['announcement_enabled'] && $set['announcement_text'])
  @include('site.partials.announcement')
  @endif
  @include('site.partials.header')

  <main id="main">
@yield('content')
  </main>

  @include('site.partials.footer')
</div>
<script defer src="{{ \App\Support\Asset::v('script.js') }}"></script>
</body>
</html>
