<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'LinkCash - Best URL Shortener To Earn Money')</title>
  <meta name="description" content="@yield('description', 'Free URL shortener where every valid visit earns you money.')">
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,700,900|Lato:400,700,900,400i&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="@yield('page')" class="@yield('body_class')">

<x-site.navbar />

@yield('content')

<x-site.footer />

@stack('scripts')
</body>
</html>
