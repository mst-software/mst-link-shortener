@extends('layouts.member')

@section('title', 'Notifications')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <i class="fa fa-bullhorn"></i>
      <h3 class="box-title">Notifications</h3>
    </div>
    <div class="box-body chat">
      @forelse ($notices as $notice)
        <div class="item">
          <p class="announcement">
            <span>
              <small class="text-muted">
                <i class="fa fa-clock"></i> {{ ($notice->published_at ?? $notice->created_at)->format('d/m/Y H:i') }}
              </small>
              <h3><i class="fa fa-angle-double-right"></i> {{ $notice->title }}</h3>
            </span>
          </p>
          <div>{!! nl2br(e($notice->body)) !!}</div>
        </div>
      @empty
        <p class="text-center text-muted">There are no notifications yet.</p>
      @endforelse

      {{ $notices->links('pagination::bootstrap-4') }}
    </div>
  </div>
@endsection
