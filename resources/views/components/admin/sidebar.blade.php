@php
  $menu = [
      ['label' => 'Dashboard', 'icon' => 'tachometer-alt', 'route' => 'admin.dashboard'],
      ['label' => 'Settings', 'icon' => 'cogs', 'route' => 'admin.settings.edit'],
      ['label' => 'Rates', 'icon' => 'percentage', 'route' => 'admin.rates.index'],
      ['label' => 'Ad slots', 'icon' => 'ad', 'route' => 'admin.ad-slots.index'],
      ['label' => 'Blocked networks', 'icon' => 'ban', 'route' => 'admin.blocked-networks.index'],
      ['label' => 'Fraud flags', 'icon' => 'flag', 'route' => 'admin.fraud-flags.index'],
      ['label' => 'Links', 'icon' => 'link', 'route' => 'admin.links.index'],
      ['label' => 'Withdrawals', 'icon' => 'dollar-sign', 'route' => 'admin.withdrawals'],
      ['label' => 'Notices', 'icon' => 'bell', 'route' => 'admin.notices'],
      ['label' => 'Support', 'icon' => 'life-ring', 'route' => 'admin.support'],
  ];
@endphp

<aside class="main-sidebar">
  <section class="sidebar">
    <ul class="sidebar-menu">
      @foreach ($menu as $item)
        @if (Route::has($item['route']))
          <li @class(['active' => request()->routeIs($item['route'])])>
            <a href="{{ route($item['route']) }}">
              <i class="fa fa-{{ $item['icon'] }}"></i>
              <span>{{ $item['label'] }}</span>
            </a>
          </li>
        @endif
      @endforeach
    </ul>
  </section>
</aside>
