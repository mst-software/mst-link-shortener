<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') - LinkCash</title>
  <meta name="description" content="@yield('description')">
  <meta name="theme-color" content="#038018">
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  @vite(['resources/css/auth.css', 'resources/js/auth.js'])
</head>
<body class="hold-transition login-page">

<main class="login-box">
  <div class="login-logo">
    <a href="{{ route('home') }}"><img src="{{ Vite::asset('resources/images/logo-poppins-dark.png') }}" alt="LinkCash"></a>
  </div>
  <div class="login-box-body">
    @yield('content')
  </div>

  <ul class="auth-trust">
    <li>Secure connection</li>
    <li>Free to join</li>
    <li>Payouts from $5</li>
  </ul>
  <p class="auth-back"><a href="{{ route('home') }}">&larr; Back to the home page</a></p>
</main>

</body>
</html>
