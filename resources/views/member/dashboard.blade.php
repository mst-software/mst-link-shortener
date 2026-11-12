@extends('layouts.member')

@section('title', 'Overview')

@section('content')
  <h3>Statistics <span class="small">(<a href="{{ route('member.statistic') }}">View details</a>)</span></h3>

  <div class="row">
    <div class="col-lg-3 col-xs-6">
      <x-member.stat-box color="orange" label="Today" :value="\App\Support\Money::format(0)" icon="chart-bar" />
    </div>
    <div class="col-lg-3 col-xs-6">
      <x-member.stat-box color="aqua" label="Yesterday" :value="\App\Support\Money::format(0)" icon="shopping-bag" />
    </div>
    <div class="col-lg-3 col-xs-6">
      <x-member.stat-box color="green" label="This month" :value="\App\Support\Money::format(0)" icon="exchange-alt" />
    </div>
    <div class="col-lg-3 col-xs-6">
      <x-member.stat-box color="red" label="Balance" :value="\App\Support\Money::format(auth()->user()->balance_micro)" icon="dollar-sign" />
    </div>
  </div>
@endsection
