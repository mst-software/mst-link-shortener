@props(['title', 'subtitle' => null])

<x-hero inner>
  <h1 class="intro-lead-in anim-up">{{ $title }}</h1>
  @if ($subtitle)
    <p class="intro-sub anim-up">{{ $subtitle }}</p>
  @endif
</x-hero>
