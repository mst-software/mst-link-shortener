<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('app.name').' - Best URL Shortener To Earn Money')</title>
  <meta name="description" content="@yield('description', 'Free URL shortener where every valid visit earns you money.')">
  <link rel="icon" type="image/png" href="{{ \App\Services\Branding::imageUrl(\App\Enums\BrandAsset::Favicon) }}">
  <meta name="theme-color" content="{{ \App\Services\Branding::color() }}">
  @if ($shareImageUrl = \App\Services\Branding::imageUrl(\App\Enums\BrandAsset::ShareImage))
    <meta property="og:image" content="{{ $shareImageUrl }}">
    <meta name="twitter:card" content="summary_large_image">
  @endif
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <x-site.brand-style />
</head>
<body data-page="@yield('page')" class="@yield('body_class')">

<x-site.navbar />

@yield('content')

<x-site.footer />

<x-site.notice-banner />

@stack('scripts')
</body>
</html>
