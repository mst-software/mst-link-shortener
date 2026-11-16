{{-- Sub-navigation of the settings area; pages come from App\Enums\SettingsPage. --}}
<div class="box box-solid settings-nav">
  <div class="box-body">
    <ul class="nav nav-pills">
      @foreach (\App\Enums\SettingsPage::cases() as $page)
        @if (Route::has($page->routeName()))
          <li @class(['active' => request()->routeIs('admin.settings.'.$page->value.'.*')])>
            <a href="{{ route($page->routeName()) }}"><i class="fa fa-{{ $page->icon() }}"></i> {{ $page->label() }}</a>
          </li>
        @endif
      @endforeach
    </ul>
  </div>
</div>
