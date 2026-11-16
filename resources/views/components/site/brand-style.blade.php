{{-- The brand colour chosen on the admin Appearance page, generated from a validated #rrggbb colour only. Include it after @vite so it overrides tokens.css. --}}
@php($brandCss = \App\Services\Branding::css())
@if ($brandCss !== '')
  <style>{!! $brandCss !!}</style>
@endif
