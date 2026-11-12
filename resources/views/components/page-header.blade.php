@props(['title', 'subtitle' => null])

<x-hero inner>
  <div class="intro-lead-in anim-zoom">{{ $title }}</div>
  @if ($subtitle)
    <p class="intro-sub anim-up">{{ $subtitle }}</p>
  @endif
</x-hero>
