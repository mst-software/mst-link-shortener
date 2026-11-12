@props(['subheading', 'heading', 'subheadingClass' => ''])

<div {{ $attributes->class(['section-title', 'reveal']) }}>
  <h3 class="section-subheading {{ $subheadingClass }}">{{ $subheading }}</h3>
  <h2 class="section-heading">{!! $heading !!}</h2>
</div>
