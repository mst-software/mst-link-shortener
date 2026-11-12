@php($member = auth()->user())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title') - LinkCash</title>
  <link rel="icon" type="image/png" href="{{ Vite::asset('resources/images/favicon.png') }}">
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic&display=swap" rel="stylesheet">
  @vite(['resources/css/member.css', 'resources/js/member.js'])
</head>
<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">

  <header class="main-header">
    <a href="{{ route('member.dashboard') }}" class="logo">
      <span class="logo-mini"><b>M</b></span>
      <span class="logo-lg"><b>Member</b></span>
    </a>
    <nav class="navbar navbar-static-top" role="navigation">
      <a href="#" class="sidebar-toggle" role="button">
        <span class="sr-only">Toggle navigation</span>
      </a>
      <a href="{{ route('home') }}" class="member-brand hidden-xs">
        <img src="{{ Vite::asset('resources/images/logo-poppins-white.png') }}" alt="LinkCash">
      </a>
      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          @php($latestNotices = \App\Models\Notice::query()->published()->limit(3)->get(['id', 'title', 'published_at', 'created_at']))
          <li class="dropdown notifications-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-label="Notifications">
              <i class="fa fa-bell"></i>
              @if ($latestNotices->isNotEmpty())
                <span class="label label-warning">{{ $latestNotices->count() }}</span>
              @endif
            </a>
            <ul class="dropdown-menu">
              <li class="header">Latest notifications</li>
              <li>
                <ul class="menu">
                  @forelse ($latestNotices as $latestNotice)
                    <li>
                      <a href="{{ route('member.notify') }}">
                        <i class="fa fa-bullhorn text-aqua"></i> {{ \Illuminate\Support\Str::limit($latestNotice->title, 40) }}
                        <small class="text-muted">{{ ($latestNotice->published_at ?? $latestNotice->created_at)->format('d/m/Y') }}</small>
                      </a>
                    </li>
                  @empty
                    <li><a href="{{ route('member.notify') }}">No notifications yet</a></li>
                  @endforelse
                </ul>
              </li>
              <li class="footer"><a href="{{ route('member.notify') }}">View all</a></li>
            </ul>
          </li>
          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-user-circle user-icon"></i>
              <span>{{ $member->fullname ?: $member->username }}</span>
            </a>
            <ul class="dropdown-menu">
              <li class="user-footer">
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

  <x-member.sidebar />

  <div class="content-wrapper">
    <x-member.page-header>
      <x-slot:title>@yield('title')</x-slot:title>
      @hasSection('breadcrumb')
        @yield('breadcrumb')
      @else
        <li class="active">@yield('title')</li>
      @endif
    </x-member.page-header>

    <section class="content">
      <x-member.shorten-box :default-level="$member->default_level" />

      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      @yield('content')
    </section>
  </div>

  <footer class="main-footer">
    <div class="pull-right hidden-xs"><b>Version</b> 1.0</div>
    <strong>Copyright &copy; {{ now()->year }} <a href="{{ route('home') }}">LinkCash</a>.</strong>
  </footer>

</div>

@stack('scripts')
@yield('scripts')
</body>
</html>
