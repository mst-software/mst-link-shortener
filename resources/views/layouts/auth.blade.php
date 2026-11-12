<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') - LinkCash</title>
  <meta name="description" content="@yield('description')">
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <link href="//fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic" rel="stylesheet">
  @vite(['resources/css/auth.css', 'resources/js/auth.js'])
</head>
<body class="hold-transition login-page">

<div class="login-box">
  <div class="login-logo">
    <a href="{{ route('home') }}"><img src="{{ Vite::asset('resources/images/logo-poppins-dark.png') }}" alt="LinkCash"></a>
  </div>
  <div class="login-box-body">
    @yield('content')
  </div>
</div>

</body>
</html>
