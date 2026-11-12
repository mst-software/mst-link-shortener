<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=0.5, maximum-scale=3">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex,nofollow">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>@yield('title')</title>
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;0,900;1,400;1,700&display=swap" rel="stylesheet">
  @vite(['resources/css/visit.css', 'resources/js/visit.js'])
</head>
<body class="lk-body captcha-page">

<nav id="mainNav" class="lk-navbar">
  <div class="lk-container lk-navbar-inner">
    <a class="lk-brand" href="{{ route('home') }}" aria-label="LinkCash home">
      <img src="{{ Vite::asset('resources/images/logo-poppins-white.png') }}" alt="LinkCash" width="150" height="32">
    </a>
    <ul class="lk-nav">
      <li><a href="{{ route('home') }}">Home</a></li>
    </ul>
  </div>
</nav>

@yield('content')

<section class="lk-cta-wrap">
  <div class="lk-container">
    <div class="lk-cta-band">
      <h3>Ready to earn money?</h3>
      <p>Create a free account and shorten your first link in less than a minute.</p>
      <a href="{{ route('register') }}" class="lk-btn-cta">Sign up for free</a>
    </div>
  </div>
</section>

<footer class="lk-footer">
  <div class="lk-footer-top">
    <div class="lk-container">
      <div class="lk-footer-cols">
        <div class="lk-footer-brand">
          <a href="{{ route('home') }}"><img src="{{ Vite::asset('resources/images/logo-poppins-white.png') }}" alt="LinkCash" height="44"></a>
          <p>Shorten your links, share them anywhere and get paid for every valid view. Clear rates, fast payouts and a support team that answers.</p>
          <div class="lk-pay-icons">
            <img src="{{ Vite::asset('resources/images/banktransfer.png') }}" alt="Bank transfer">
            <img src="{{ Vite::asset('resources/images/paypal.png') }}" alt="PayPal">
            <img src="{{ Vite::asset('resources/images/bitcoin.png') }}" alt="Bitcoin">
          </div>
        </div>
        <div>
          <h5>Quick links</h5>
          <ul class="lk-footer-links">
            <li><a href="{{ route('home') }}">Home</a></li>
            <li><a href="{{ route('payout-rates') }}">Publisher Rates</a></li>
            <li><a href="{{ route('blog') }}">Blog</a></li>
            <li><a href="{{ route('login') }}">Sign In</a></li>
            <li><a href="{{ route('register') }}">Sign Up</a></li>
          </ul>
        </div>
        <div>
          <h5>Legal</h5>
          <ul class="lk-footer-links">
            <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
            <li><a href="{{ route('terms') }}">Terms of Use</a></li>
            <li><a href="{{ route('payout-rates') }}#view-policy">View Counting Policy</a></li>
            <li><a href="{{ route('home') }}#contact">Contact Us</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="lk-footer-sep"><div class="lk-container"></div></div>

  <div class="lk-footer-bottom">
    <div class="lk-container lk-footer-bottom-inner">
      <div>Copyright &copy; LinkCash {{ now()->year }}. All rights reserved.</div>
      <div class="lk-dev">Developed by <a href="https://www.facebook.com/mstsoftware.vn" target="_blank" rel="noopener">MST Software</a></div>
    </div>
  </div>
</footer>

@stack('scripts')
</body>
</html>
