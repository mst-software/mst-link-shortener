@props(['title', 'subtitle' => null, 'updated'])

<x-page-header :title="$title" :subtitle="$subtitle" />

<section class="page-body">
  <div class="container">
    <div class="flex flex-wrap -mx-[15px]">
      <aside class="w-full md:w-1/4 px-[15px] mb-6">
        <nav class="toc md:sticky md:top-[90px]" id="toc"></nav>
      </aside>
      <div class="w-full md:w-3/4 px-[15px] doc" id="doc">
        <span class="updated">Last updated: {{ $updated }}</span>
        {{-- Sample content: have a lawyer review it before launch --}}

        {{ $slot }}
      </div>
    </div>
  </div>
</section>

@push('scripts')
  @vite('resources/js/legal.js')
@endpush
