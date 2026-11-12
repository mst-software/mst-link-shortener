@extends('layouts.member')

@section('title', 'Statistics')

@section('content')
  <x-member.month-picker :months="$months" :selected="$month" />

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
@endsection

@push('scripts')
  @vite('resources/js/member-statistic.js')
@endpush
