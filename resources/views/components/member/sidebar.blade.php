@php
  $menu = [
      ['label' => 'Overview', 'icon' => 'tachometer-alt', 'route' => 'member.dashboard'],
      ['label' => 'Notifications', 'icon' => 'bell', 'route' => 'member.notify'],
      ['label' => 'Statistics', 'icon' => 'chart-bar', 'route' => 'member.statistic'],
      ['label' => 'Top Links', 'icon' => 'fire', 'route' => 'member.popular-link'],
      ['label' => 'Links', 'icon' => 'link', 'route' => 'member.links', 'children' => [
          ['label' => 'All Links', 'route' => 'member.links'],
          ['label' => 'Hidden Links', 'route' => 'member.links.hidden'],
      ]],
      ['label' => 'Withdraws', 'icon' => 'dollar-sign', 'route' => 'member.withdraws'],
      ['label' => 'Tools', 'icon' => 'wrench', 'route' => 'member.tools.quick', 'children' => [
          ['label' => 'Quick Link', 'route' => 'member.tools.quick'],
          ['label' => 'Mass Shrinker', 'route' => 'member.tools.mass-shrinker'],
          ['label' => 'Full Page Script', 'route' => 'member.tools.full'],
          ['label' => 'Developers API', 'route' => 'member.tools.api'],
      ]],
      ['label' => 'Referrals', 'icon' => 'exchange-alt', 'route' => 'member.referrals'],
      ['label' => 'Settings', 'icon' => 'cogs', 'route' => 'member.user.profile', 'children' => [
          ['label' => 'Profile', 'route' => 'member.user.profile'],
          ['label' => 'Change Password', 'route' => 'member.user.change-password'],
          ['label' => 'Change Email', 'route' => 'member.user.change-email'],
      ]],
      ['label' => 'Support', 'icon' => 'life-ring', 'route' => 'member.form.support'],
  ];
@endphp

<aside class="main-sidebar">
  <section class="sidebar">
    <button type="button" class="btn btn-block btn-social btn-github btn-lg shorten-button">
      <i class="fa fa-paper-plane"></i> <span>Shorten a new link</span>
    </button>
    <ul class="sidebar-menu">
      @foreach ($menu as $item)
        @if (isset($item['children']))
          @php($isOpen = collect($item['children'])->contains(fn (array $child): bool => request()->routeIs($child['route'])))
          <li @class(['treeview', 'active' => $isOpen])>
            <a href="{{ route($item['route']) }}">
              <i class="fa fa-{{ $item['icon'] }}"></i>
              <span>{{ $item['label'] }}</span>
              <i class="fa fa-angle-left pull-right"></i>
            </a>
            <ul @class(['treeview-menu', 'menu-open' => $isOpen])>
              @foreach ($item['children'] as $child)
                <li @class(['active' => request()->routeIs($child['route'])])>
                  <a href="{{ route($child['route']) }}">{{ $child['label'] }}</a>
                </li>
              @endforeach
            </ul>
          </li>
        @else
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
