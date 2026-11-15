@php($admin = auth()->user())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title') - Admin - LinkCash</title>
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic&display=swap" rel="stylesheet">
  @vite(['resources/css/admin.css', 'resources/js/member.js'])
</head>
<body class="hold-transition skin-blue sidebar-mini admin-area">
<div class="wrapper">

  <header class="main-header">
    <a href="{{ route('admin.dashboard') }}" class="logo">
      <span class="logo-mini"><b>A</b></span>
      <span class="logo-lg"><b>Admin</b></span>
    </a>
    <nav class="navbar navbar-static-top" role="navigation">
      <a href="#" class="sidebar-toggle" role="button">
        <span class="sr-only">Toggle navigation</span>
      </a>
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-user-circle user-icon"></i>
              <span>{{ $admin->username }}</span>
            </a>
            <ul class="dropdown-menu">
              <li class="user-footer">
                <a href="{{ route('member.dashboard') }}" class="btn btn-default btn-flat pull-left">Member area</a>
                <form method="post" action="{{ route('logout') }}" class="pull-right">
                  @csrf
                  <button type="submit" class="btn btn-default btn-flat">Sign out</button>
                </form>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </nav>
  </header>

  <x-admin.sidebar />

  <div class="content-wrapper">
    <section class="content-header">
      <h1>@yield('title')</h1>
    </section>

    <section class="content">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
      @endif
      @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      @yield('content')
    </section>
  </div>

  <footer class="main-footer">
    <strong>Copyright &copy; {{ now()->year }} <a href="{{ route('home') }}">LinkCash</a>.</strong>
  </footer>

</div>

@stack('scripts')
@yield('scripts')
</body>
</html>
