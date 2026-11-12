@props(['color' => 'aqua', 'label', 'value', 'icon' => 'chart-bar'])

@php
  $background = match ($color) {
      'orange', 'yellow' => 'bg-yellow',
      'green' => 'bg-green',
      'red' => 'bg-red',
      default => 'bg-aqua',
  };
@endphp

<div {{ $attributes->class(['small-box', $background]) }}>
  <div class="inner">
    <h3>{{ $value }}</h3>
    <p>{{ $label }}</p>
  </div>
  <div class="icon">
    <i class="fa fa-{{ $icon }}"></i>
  </div>
</div>
