@props(['title'])

<div {{ $attributes->class(['panel']) }}>
  <h4>{{ $title }}</h4>
  {{ $slot }}
</div>
