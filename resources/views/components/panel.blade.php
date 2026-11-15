@props(['title'])

<div {{ $attributes->class(['panel']) }}>
  <h3 class="panel-title">{{ $title }}</h3>
  {{ $slot }}
</div>
