<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#faf7ef">
  <title>@yield('title')</title>
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/visit.css', 'resources/js/visit.js'])
</head>
<body class="unlock-body" data-mode="loading">

<a href="#visit" class="unlock-skip">Skip to the unlock panel</a>

<header class="unlock-top">
  <div class="unlock-shell unlock-top-inner">
    <a href="{{ route('home') }}" class="unlock-logo">
      <img src="{{ Vite::asset('resources/images/logo-poppins-dark.png') }}" alt="LinkCash" width="152" height="32">
    </a>
    <nav aria-label="Main">
      <ul class="unlock-nav">
        <li><a href="{{ route('home') }}">Home</a></li>
        <li><a href="{{ route('payout-rates') }}">Payout rates</a></li>
      </ul>
    </nav>
  </div>
</header>

<main class="unlock-main">
  @yield('content')
</main>

<footer class="unlock-footer">
  <div class="unlock-shell unlock-footer-inner">
    <a href="{{ route('home') }}" class="unlock-footer-logo">
      <img src="{{ Vite::asset('resources/images/logo-icon.png') }}" alt="" width="28" height="28">
      <span>LinkCash</span>
    </a>
    <ul class="unlock-footer-links">
      <li><a href="{{ route('home') }}">Home</a></li>
      <li><a href="{{ route('payout-rates') }}">Payout Rates</a></li>
      <li><a href="{{ route('home') }}#contact">Contact</a></li>
    </ul>
    <p class="unlock-copyright">&copy; {{ now()->year }} LinkCash. All rights reserved.</p>
  </div>
</footer>

@stack('scripts')
</body>
</html>
