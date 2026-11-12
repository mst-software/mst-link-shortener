@extends('layouts.app')

@section('title', 'Blog - LinkCash')
@section('description', 'News, tips and guides for LinkCash users.')
@section('page', 'blog')

@section('content')
  <x-page-header title="Blog" subtitle="News, tips and guides to help you earn more from every link." />

  <section class="page-body">
    <div class="container">
      <div class="chips reveal">
        <button class="chip on" data-filter="all" type="button">All</button>
        @foreach ($categories as $key => $name)
          <button class="chip" data-filter="{{ $key }}" type="button">{{ $name }}</button>
        @endforeach
      </div>

      <div class="flex flex-wrap -mx-[15px]" id="postGrid">
        @foreach ($posts as $index => $post)
          <x-blog-card
            :index="$index"
            :category="$post['category']"
            :label="$post['label']"
            :date="$post['date']"
            :icon="$post['icon']"
            :title="$post['title']"
            :excerpt="$post['excerpt']"
          >{!! $post['body'] !!}</x-blog-card>
        @endforeach
      </div>
    </div>
  </section>

  <div id="postModal" role="dialog" aria-modal="true">
    <div class="box">
      <button class="close" type="button" aria-label="Close">&times;</button>
      <div class="blog-meta" id="pMeta"></div>
      <h3 id="pTitle"></h3>
      <div id="pBody"></div>
    </div>
  </div>
@endsection

@push('scripts')
  @vite('resources/js/blog.js')
@endpush
