@extends('layouts.member')

@section('title', 'Statistics')

@section('content')
  <x-member.month-picker :months="$months" :selected="$month" :range="$range" />

  <h3>Monthly report</h3>

  <div id="statistic" data-url="{{ route('member.dashboard.data') }}" data-date="{{ $dataDate }}">
    <div class="row">
      <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-yellow">
          <div class="inner"><h3 id="total_view"><i class="fa fa-spin fa-spinner" style="font-size: 30px;"></i></h3><p>Total views</p></div>
          <div class="icon"><i class="fa fa-chart-bar"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-aqua">
          <div class="inner"><h3 id="total_publisher_earn"><i class="fa fa-spin fa-spinner" style="font-size: 30px;"></i></h3><p>Total income</p></div>
          <div class="icon"><i class="fa fa-shopping-bag"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-green">
          <div class="inner"><h3 id="total_ref_earn"><i class="fa fa-spin fa-spinner" style="font-size: 30px;"></i></h3><p>Referral income</p></div>
          <div class="icon"><i class="fa fa-exchange-alt"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-red">
          <div class="inner"><h3 id="average_cpm"><i class="fa fa-spin fa-spinner" style="font-size: 30px;"></i></h3><p>Average CPM</p></div>
          <div class="icon"><i class="fa fa-dollar-sign"></i></div>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-body">
        <legend id="report-chart">Income chart</legend>
        <div id="container" style="position: relative; height: 320px;">
          <canvas id="income-chart" style="width: 100%; height: 100%;"></canvas>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-body">
        <legend>Daily report</legend>
        <p class="text-muted small">Figures are updated a few minutes after each view.</p>
        <div class="table-responsive">
          <table class="table table-bordered detail-table">
            <thead>
              <tr>
                <th class="text-center">Day</th>
                <th class="text-center">Views</th>
                <th class="text-center">Earnings ($)</th>
                <th class="text-center">CPM</th>
                <th class="text-center">Referral earnings</th>
              </tr>
            </thead>
            <tbody id="detail-rows">
              <tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <h3 id="breakdowns">View breakdowns: last {{ $range }} days (UTC)</h3>
  <p class="text-muted small">This section always covers the last {{ $range }} days up to now, regardless of the month selected above.</p>

  <div class="text-center">
    <form method="get" action="{{ route('member.statistic') }}#breakdowns" class="form form-inline" style="margin: 10px auto;">
      <input type="hidden" name="month" value="{{ $month }}">
      <div class="form-group">
        <select name="range" class="form-control" onchange="this.form.submit()" aria-label="Breakdown range">
          @foreach ($ranges as $option)
            <option value="{{ $option }}" @selected($option === $range)>Last {{ $option }} days</option>
          @endforeach
        </select>
      </div>
    </form>
  </div>

  <p class="text-muted small">
    Valid views since {{ $breakdowns['from']->format('d/m/Y') }} (UTC). Breakdowns are built from detailed view records, which are
    only kept for the last {{ $breakdowns['retention_days'] }} days.
    @if ($breakdowns['from']->lt($breakdowns['retained_from']))
      <strong>Days before {{ $breakdowns['retained_from']->format('d/m/Y') }} are not included.</strong>
    @endif
  </p>

  <div class="row">
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-header with-border">
          <i class="fa fa-globe"></i>
          <h3 class="box-title">By country</h3>
        </div>
        <div class="box-body">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th class="text-center">Country</th>
                  <th class="text-center">Views</th>
                  <th class="text-center">Earnings ($)</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($breakdowns['by_country'] as $row)
                  <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="text-right">{{ number_format($row['views']) }}</td>
                    <td class="text-right">{{ number_format($row['earn_micro'] / \App\Support\Money::MICRO_PER_USD, 3) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted">No valid views in this period.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-header with-border">
          <i class="fa fa-list"></i>
          <h3 class="box-title">By level</h3>
        </div>
        <div class="box-body">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th class="text-center">Level</th>
                  <th class="text-center">Views</th>
                  <th class="text-center">Earnings ($)</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($breakdowns['by_level'] as $row)
                  <tr>
                    <td>Level {{ $row['level'] }}</td>
                    <td class="text-right">{{ number_format($row['views']) }}</td>
                    <td class="text-right">{{ number_format($row['earn_micro'] / \App\Support\Money::MICRO_PER_USD, 3) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted">No valid views in this period.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="box box-success">
    <div class="box-header with-border">
      <i class="fa fa-fire"></i>
      <h3 class="box-title">Top 10 links</h3>
    </div>
    <div class="box-body">
      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th class="text-center">No</th>
              <th class="text-center">Links</th>
              <th class="text-center">Shorten Link</th>
              <th class="text-center">Views</th>
              <th class="text-center">Earnings ($)</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($breakdowns['by_link'] as $row)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td style="max-width: 500px; word-break: break-all;">{{ $row['link']->url }}</td>
                <td>{{ url($row['link']->alias) }}</td>
                <td class="text-right">{{ number_format($row['views']) }}</td>
                <td class="text-right">{{ number_format($row['earn_micro'] / \App\Support\Money::MICRO_PER_USD, 3) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted">No valid views in this period.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  @vite('resources/js/member-statistic.js')
@endpush
