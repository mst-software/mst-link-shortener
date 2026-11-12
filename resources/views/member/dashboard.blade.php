@extends('layouts.member')

@section('title', 'Overview')

@section('content')
  <h3>Statistics <span class="small">(<a href="{{ route('member.statistic', ['month' => $month]) }}">View details</a>)</span></h3>

  <div class="row">
    <div class="col-lg-3 col-xs-6">
      <x-member.stat-box color="orange" label="Today" :value="$today" icon="chart-bar" />
    </div>
    <div class="col-lg-3 col-xs-6">
      <x-member.stat-box color="aqua" label="Yesterday" :value="$yesterday" icon="shopping-bag" />
    </div>
    <div class="col-lg-3 col-xs-6">
      <x-member.stat-box color="green" label="This month" :value="$thisMonth" icon="exchange-alt" />
    </div>
    <div class="col-lg-3 col-xs-6">
      <x-member.stat-box color="red" label="Balance" :value="$balance" icon="dollar-sign" />
    </div>
  </div>

  <div class="box box-primary">
    <div class="box-header with-border">
      <i class="fa fa-bullhorn"></i>
      <h3 class="box-title">Notices</h3>
    </div>
    <div class="box-body chat">
      @forelse ($notices as $notice)
        <div class="item">
          <p class="announcement">
            <span>
              <small class="text-muted">
                <i class="fa fa-clock"></i> {{ ($notice->published_at ?? $notice->created_at)->format('H:i d/m/Y') }}
              </small>
              <h3><i class="fa fa-angle-double-right"></i> {{ $notice->title }}</h3>
            </span>
          </p>
          <div>{!! nl2br(e($notice->body)) !!}</div>
        </div>
      @empty
        <p class="text-center text-muted">There are no notices yet.</p>
      @endforelse

      @if ($notices->isNotEmpty())
        <p class="text-center"><a href="{{ route('member.notify') }}">View all notices</a></p>
      @endif
    </div>
  </div>
@endsection
