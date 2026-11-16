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
  <meta name="theme-color" content="#038018">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  {{-- A broken deploy without public/build must still show this page, so the assets are only used when they exist. --}}
  @if (is_file(public_path('build/manifest.json')) || is_file(public_path('hot')))
    <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @else
    <style>
      body { margin: 0; font-family: Inter, system-ui, sans-serif; background: #022c0b; color: #fff; }
      header { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 24px; text-align: center; }
      h1 { margin: 0 0 12px; font-size: 40px; }
      p { max-width: 620px; margin: 0 auto 16px; color: rgb(255 255 255 / .82); }
      a { display: inline-block; margin: 8px; padding: 12px 26px; border-radius: 12px; background: #b8de01; color: #022c0b; font-weight: 600; text-decoration: none; }
    </style>
  @endif
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
