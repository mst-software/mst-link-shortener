<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title')</title>
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700,900&display=swap" rel="stylesheet">
  @vite(['resources/css/visit.css', 'resources/js/visit.js'])
</head>
<body class="visit-body">

<header class="visit-header">
  <div class="visit-container visit-header-inner">
    <a href="{{ route('home') }}" class="visit-logo">
      <img src="{{ Vite::asset('resources/images/logo-poppins-white.png') }}" alt="LinkCash">
    </a>
    <nav aria-label="Main">
      <ul class="visit-menu">
        <li><a href="{{ route('home') }}">Home</a></li>
      </ul>
    </nav>
  </div>
</header>

<main class="visit-main">
  @yield('content')
</main>

<footer class="visit-footer">
  <div class="visit-container">
    <div class="visit-footer-top">
      <a href="{{ route('home') }}" class="visit-footer-logo">
        <img src="{{ Vite::asset('resources/images/logo-poppins-dark.png') }}" alt="LinkCash">
      </a>
      <ul class="visit-footer-links">
        <li><a href="{{ route('home') }}">Home</a></li>
        <li><a href="{{ route('payout-rates') }}">Payout Rates</a></li>
        <li><a href="{{ route('home') }}#contact">Contact</a></li>
      </ul>
    </div>
    <p class="visit-copyright">Copyright &copy; {{ now()->year }} <a href="{{ route('home') }}">LinkCash</a>. All rights reserved.</p>
  </div>
</footer>

@stack('scripts')
</body>
</html>
