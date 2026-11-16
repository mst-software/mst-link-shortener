{{--
  Shared layout of the error pages. It must not touch the database or the session (no navbar, footer or
  notice banner components), so it still renders when the database is down or during `php artisan down`.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>@yield('title') - {{ config('app.name') }}</title>
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <meta name="theme-color" content="#038018">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="error">

<x-hero inner class="min-h-screen">
  <p class="intro-sub anim-up">Error @yield('code')</p>
  <h1 class="intro-lead-in anim-up">@yield('title')</h1>
  <p class="intro-sub anim-up">@yield('message')</p>
  <div class="anim-up mt-8 flex flex-wrap justify-center gap-3">
    @yield('actions')
    <a href="{{ route('home') }}" class="btn-cta btn-ripple">Back to home</a>
  </div>
</x-hero>

</body>
</html>
