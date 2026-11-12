@php
  $menu = [
      ['label' => 'Overview', 'icon' => 'tachometer-alt', 'route' => 'advertiser.dashboard'],
  ];
@endphp

<aside class="main-sidebar">
  <section class="sidebar">
    <ul class="sidebar-menu">
      @foreach ($menu as $item)
        <li @class(['active' => request()->routeIs($item['route'])])>
          <a href="{{ route($item['route']) }}">
            <i class="fa fa-{{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
          </a>
        </li>
      @endforeach
    </ul>
  </section>
</aside>
