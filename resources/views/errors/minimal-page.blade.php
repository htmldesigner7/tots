<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $title }} | Ananya Tots</title>
<link rel="stylesheet" href="{{ asset('style.css') }}">
</head>
<body id="top">
<main id="main" style="min-height:70vh;display:grid;place-items:center;text-align:center;padding:40px 20px">
  <div>
    <img src="{{ asset('assets/img/logo.png') }}" width="136" height="70" alt="Ananya Tots">
    <h1 style="margin:24px 0 8px"><span class="h-display">{{ $title }}</span></h1>
    <p>{{ $text }}</p>
    <p><a class="btn" href="{{ url('/') }}">Back to Home</a></p>
  </div>
</main>
</body>
</html>
