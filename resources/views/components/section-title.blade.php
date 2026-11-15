@props(['subheading', 'heading', 'subheadingClass' => ''])

<div {{ $attributes->class(['section-title', 'reveal']) }}>
  <p class="section-subheading {{ $subheadingClass }}">{{ $subheading }}</p>
  <h2 class="section-heading">{!! $heading !!}</h2>
</div>
