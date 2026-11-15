@extends('layouts.advertiser')

@section('title', 'Overview')

@section('content')
  @if ($lowBalance)
    <div class="alert alert-warning">
      <strong>Your balance is running low.</strong> Campaigns pause automatically when the balance cannot pay for another visit.
      <a href="{{ route('advertiser.wallet') }}" class="alert-link">Top up now</a>.
    </div>
  @endif

  @php
    $cards = [
        ['green', 'wallet', 'Balance', '$'.number_format(auth()->user()->balance_micro / 1_000_000, 2)],
        ['yellow', 'chart-bar', 'Spent today', '$'.number_format($spentToday / 1_000_000, 2)],
        ['aqua', 'calendar', 'Spent last 7 days', '$'.number_format($spentWeek / 1_000_000, 2)],
    ];
  @endphp

  <div class="row">
    @foreach ($cards as [$color, $icon, $label, $value])
      <div class="col-md-4 col-sm-6">
        <div class="info-box">
          <span class="info-box-icon bg-{{ $color }}"><i class="fa fa-{{ $icon }}"></i></span>
          <div class="info-box-content">
            <span class="info-box-text">{{ $label }}</span>
            <span class="info-box-number">{{ $value }}</span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Campaigns</h3>
      <div class="box-tools">
        <a href="{{ route('advertiser.wallet') }}" class="btn btn-default btn-sm"><i class="fa fa-wallet"></i> Top up</a>
        <a href="{{ route('advertiser.campaigns.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> New campaign</a>
      </div>
    </div>
    <div class="box-body">
      <ul class="list-inline">
        @foreach ($campaignCounts as $label => $total)
          <li><strong>{{ $label }}</strong> <span class="badge">{{ $total }}</span></li>
        @endforeach
      </ul>
    </div>
  </div>
@endsection
