@props(['index', 'category', 'label', 'date', 'icon', 'title', 'excerpt'])

<div class="w-full sm:w-1/2 md:w-1/3 px-[15px] mb-8 reveal post" data-cat="{{ $category }}">
  <article class="blog-card" data-post="{{ $index }}" data-meta="{{ $date }} · {{ $label }}" tabindex="0">
    <div class="blog-thumb"><i class="sp sp-{{ $icon }}"></i></div>
    <div class="blog-body">
      <div class="blog-meta"><span class="tag-pill">{{ $label }}</span> &nbsp; {{ $date }}</div>
      <h4>{{ $title }}</h4>
      <p>{{ $excerpt }}</p>
      <span class="blog-more">Read more &rarr;</span>
    </div>
    <template class="post-body">{{ $slot }}</template>
  </article>
</div>
