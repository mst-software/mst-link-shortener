@extends('layouts.advertiser')

@section('title', $campaign->name)

@section('breadcrumb')
  <li><a href="{{ route('advertiser.campaigns.index') }}">Campaigns</a></li>
  <li class="active">{{ $campaign->name }}</li>
@endsection

@php
  use App\Enums\ReviewStatus;
  use App\Services\Advertiser\CampaignReport;

  $started = max(1, $funnel['started']);
  $steps = [
      ['Started', $funnel['started'], 'bg-aqua'],
      ['Code issued', $funnel['issued'], 'bg-blue'],
      ['Used - Counted', $funnel['counted'], 'bg-green'],
      ['Used - Not counted', $funnel['not_counted'], 'bg-red'],
  ];
  $maxDaily = max(1, collect($daily)->max(fn (array $day): int => max($day['started'], $day['issued'], $day['counted'])));
@endphp

@section('content')
  <div class="box box-solid">
    <div class="box-body">
      <span @class([
        'label',
        'label-warning' => $campaign->review_status === ReviewStatus::Pending,
        'label-success' => $campaign->review_status === ReviewStatus::Approved,
        'label-danger' => $campaign->review_status === ReviewStatus::Rejected,
      ])>{{ $campaign->review_status->label() }}</span>
      @if ($campaign->review_status === ReviewStatus::Approved)
        <span class="label {{ $campaign->is_active ? 'label-success' : 'label-default' }}">{{ $campaign->is_active ? 'Running' : 'Paused' }}</span>
      @endif
      <strong style="margin-left: 8px">{{ $campaign->keyword }}</strong> <span class="text-muted">on {{ $campaign->domain }}</span>

      <div class="pull-right">
        @if ($campaign->review_status === ReviewStatus::Approved)
          <form action="{{ route('advertiser.campaigns.toggle', $campaign) }}" method="post" style="display: inline">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-default btn-sm">{{ $campaign->is_active ? 'Pause' : 'Resume' }}</button>
          </form>
        @endif
        <a href="{{ route('advertiser.campaigns.edit', $campaign) }}" class="btn btn-default btn-sm">Edit</a>
      </div>
    </div>
  </div>

  @if ($isUnderfunded)
    <div class="alert alert-warning">
      <strong>Paused: insufficient balance.</strong> Your wallet cannot pay for another visit, so no new codes are handed out.
      <a href="{{ route('advertiser.wallet') }}" class="alert-link">Top up</a> and the campaign starts again by itself.
    </div>
  @endif

  @if ($campaign->review_status === ReviewStatus::Pending)
    <div class="alert alert-warning">This campaign is waiting for review. It will start running once it is approved.</div>
  @elseif ($campaign->review_status === ReviewStatus::Rejected)
    <div class="alert alert-danger"><strong>Rejected.</strong> {{ $campaign->review_note }}</div>
  @endif

  <form method="get" class="form-inline" style="margin-bottom: 15px">
    <label for="from">From</label>
    <input type="date" id="from" name="from" class="form-control input-sm" value="{{ $from->toDateString() }}">
    <label for="to">to</label>
    <input type="date" id="to" name="to" class="form-control input-sm" value="{{ $to->toDateString() }}">
    @if ($status)
      <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    @error('from')<span class="text-danger">{{ $message }}</span>@enderror
    @error('to')<span class="text-danger">{{ $message }}</span>@enderror
  </form>

  <div class="row">
    <div class="col-md-8">
      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Visitor journey</h3></div>
        <div class="box-body">
          @foreach ($steps as [$label, $total, $color])
            <div style="margin-bottom: 10px">
              <div>{{ $label }} <strong class="pull-right">{{ number_format($total) }}</strong></div>
              <div class="progress" style="margin: 2px 0 0">
                <div class="progress-bar {{ $color }}" style="width: {{ min(100, round($total / $started * 100)) }}%"></div>
              </div>
            </div>
          @endforeach
          <p class="text-muted" style="margin-bottom: 0">
            Not counted visits are not charged to your wallet.
          </p>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="info-box">
        <span class="info-box-icon bg-yellow"><i class="fa fa-dollar-sign"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Spent</span>
          <span class="info-box-number">${{ number_format($funnel['spent_micro'] / 1_000_000, 2) }}</span>
        </div>
      </div>
      <div class="info-box">
        <span class="info-box-icon bg-green"><i class="fa fa-wallet"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Wallet left</span>
          <span class="info-box-number">${{ number_format($balanceMicro / 1_000_000, 2) }}</span>
        </div>
      </div>
      <div class="info-box">
        <span class="info-box-icon bg-aqua"><i class="fa fa-key"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Codes today / daily limit</span>
          <span class="info-box-number">{{ number_format($issuedToday) }} / {{ $campaign->daily_limit === 0 ? 'no limit' : number_format($campaign->daily_limit) }}</span>
        </div>
      </div>
    </div>
  </div>

  <div class="box">
    <div class="box-header with-border"><h3 class="box-title">Per day</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-condensed">
        <thead><tr><th style="width: 110px">Day</th><th>Started</th><th>Code issued</th><th>Counted</th></tr></thead>
        <tbody>
          @foreach (array_reverse($daily) as $day)
            <tr>
              <td>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
              @foreach ([['started', 'bg-aqua'], ['issued', 'bg-blue'], ['counted', 'bg-green']] as [$key, $color])
                <td>
                  <div style="display: flex; align-items: center; gap: 6px">
                    <div class="{{ $color }}" style="height: 10px; width: {{ round($day[$key] / $maxDaily * 100) }}%; min-width: {{ $day[$key] > 0 ? 3 : 0 }}px"></div>
                    <span>{{ number_format($day[$key]) }}</span>
                  </div>
                </td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="box">
    <div class="box-header with-border">
      <h3 class="box-title">Visits</h3>
      <div class="box-tools">
        @foreach ([null => 'All', 'counted' => 'Counted', 'not_counted' => 'Not counted'] as $key => $label)
          <a href="{{ route('advertiser.campaigns.show', array_filter(['campaign' => $campaign, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'status' => $key])) }}"
             @class(['btn', 'btn-xs', 'btn-primary' => $status === $key, 'btn-default' => $status !== $key])>{{ $label }}</a>
        @endforeach
      </div>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead><tr><th>Time</th><th>Country</th><th>Status</th><th class="text-right">Cost</th></tr></thead>
        <tbody>
          @forelse ($visits as $visit)
            <tr>
              <td>{{ $visit->created_at->format('d/m/Y H:i') }}</td>
              <td>{{ $visit->country }}</td>
              <td>
                @if (CampaignReport::isCounted($visit))
                  <span class="text-green">Counted</span>
                @else
                  <span class="text-red">Not counted</span>
                @endif
              </td>
              <td class="text-right">${{ number_format($visit->advertiser_cost_micro / 1_000_000, 3) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted">No visits in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($visits->hasPages())
      <div class="box-footer">{{ $visits->links() }}</div>
    @endif
  </div>

  @if ($campaign->review_status === ReviewStatus::Approved)
    <div class="box box-info">
      <div class="box-header with-border"><h3 class="box-title">Embed code</h3></div>
      <div class="box-body">
        <p class="text-muted">Add this snippet to every page of your site that should show the keyword widget.</p>
        <textarea class="form-control" rows="2" readonly style="font-family: monospace">&lt;script async src="{{ url('/js/widget.js') }}" data-campaign="{{ $campaign->widget_key }}"&gt;&lt;/script&gt;</textarea>
      </div>
    </div>
  @endif
@endsection
