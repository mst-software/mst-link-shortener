@extends('layouts.advertiser')

@section('title', 'Wallet')

@section('content')
  <div class="box">
    <div class="box-body">
      <p>Balance: ${{ number_format(auth()->user()->balance_micro / 1_000_000, 2) }}</p>
    </div>
  </div>
@endsection
