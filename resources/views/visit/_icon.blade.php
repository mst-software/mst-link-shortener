{{-- Inline stroke icons for the visitor page. Usage: @include('visit._icon', ['name' => 'check', 'class' => 'optional-class']) --}}
<svg class="icon {{ $class ?? '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
  @switch($name)
    @case('lock')
      <rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2.5"/>
      @break
    @case('check')
      <path d="m5 12.5 4.5 4.5L19 7.5"/>
      @break
    @case('copy')
      <rect x="8.5" y="8.5" width="11" height="11" rx="2.5"/><path d="M15.5 8.5V6a1.5 1.5 0 0 0-1.5-1.5H6A1.5 1.5 0 0 0 4.5 6v8A1.5 1.5 0 0 0 6 15.5h2.5"/>
      @break
    @case('search')
      <circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>
      @break
    @case('external')
      <path d="M14 4.5h5.5V10"/><path d="M19.5 4.5 11 13"/><path d="M17.5 13.5V18a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 18V8A1.5 1.5 0 0 1 6 6.5h4.5"/>
      @break
    @case('globe')
      <circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.3 2.4 3.5 5.2 3.5 8.5s-1.2 6.1-3.5 8.5c-2.3-2.4-3.5-5.2-3.5-8.5s1.2-6.1 3.5-8.5Z"/>
      @break
    @case('browser')
      <rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M3.5 9h17"/><path d="M7 6.75h.01M10 6.75h.01"/>
      @break
    @case('ban')
      <circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/>
      @break
    @case('eye-off')
      <path d="M3 3l18 18"/><path d="M10.6 5.6A9.8 9.8 0 0 1 12 5.5c5 0 8.5 4.5 9 6.5-.2.8-.9 2.1-2 3.4"/><path d="M6.6 6.7C4.5 8 3.3 10.4 3 12c.5 2 4 6.5 9 6.5 1.6 0 3-.4 4.3-1.1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
      @break
    @case('arrow-right')
      <path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>
      @break
    @case('arrow-down')
      <path d="M12 5v14"/><path d="m6 13 6 6 6-6"/>
      @break
    @case('chevron')
      <path d="m6.5 9.5 5.5 5.5 5.5-5.5"/>
      @break
    @case('alert')
      <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>
      @break
    @case('clock')
      <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>
      @break
    @case('hourglass')
      <path d="M6.5 3.5h11M6.5 20.5h11"/><path d="M7.5 3.5c0 4.5 4.5 5.5 4.5 8.5s-4.5 4-4.5 8.5M16.5 3.5c0 4.5-4.5 5.5-4.5 8.5s4.5 4 4.5 8.5"/>
      @break
    @case('flag')
      <path d="M5 21V4.5"/><path d="M5 4.5h11.5l-2 4 2 4H5"/>
      @break
    @case('refresh')
      <path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 4.5v4h-4"/>
      @break
    @case('shield')
      <path d="M12 3.5 19 6v5.5c0 4.3-3 7.6-7 9-4-1.4-7-4.7-7-9V6l7-2.5Z"/><path d="m9 12 2 2 4-4"/>
      @break
    @case('play')
      <circle cx="12" cy="12" r="8.5"/><path d="m10.5 9 4.5 3-4.5 3V9Z"/>
      @break
    @case('pointer')
      <path d="M9 4.5v9"/><path d="M9 11.5V9a1.5 1.5 0 0 1 3 0v2.5"/><path d="M12 10.5a1.5 1.5 0 0 1 3 0v1.5"/><path d="M15 11.5a1.5 1.5 0 0 1 3 0V15a5.5 5.5 0 0 1-5.5 5.5h-1A5.5 5.5 0 0 1 7 18l-2.2-3.3a1.5 1.5 0 0 1 2.4-1.8L9 14.5V6a1.5 1.5 0 0 1 3 0"/>
      @break
    @case('image')
      <rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.75"/><path d="m20.5 16-4.5-4.5-8.5 8"/>
      @break
    @case('close')
      <path d="M6 6l12 12M18 6 6 18"/>
      @break
    @case('clipboard')
      <rect x="5.5" y="5" width="13" height="15.5" rx="2.5"/><path d="M9 5V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/><path d="M9 11h6M9 15h4"/>
      @break
    @case('keyboard')
      <rect x="3" y="6.5" width="18" height="11" rx="2.5"/><path d="M7 10.5h.01M11 10.5h.01M15 10.5h.01M8 14h8"/>
      @break
    @case('message')
      <path d="M20.5 12a8.5 8.5 0 0 1-12.4 7.5L3.5 20.5l1-4.4A8.5 8.5 0 1 1 20.5 12Z"/>
      @break
    @case('page-off')
      <path d="M14 3.5H7A1.5 1.5 0 0 0 5.5 5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8L14 3.5Z"/><path d="M14 3.5V8h4.5"/><path d="m10 12 4 4M14 12l-4 4"/>
      @break
    @case('code-off')
      <rect x="3.5" y="7" width="17" height="10" rx="2.5"/><path d="M7.5 12h.01M11 12h.01"/><path d="m15 10 3 4M18 10l-3 4"/>
      @break
  @endswitch
</svg>
