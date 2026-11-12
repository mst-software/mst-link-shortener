@extends('layouts.advertiser')

@section('title', 'Overview')

@section('content')
  <div class="box">
    <div class="box-body">
      <p>Welcome, {{ auth()->user()->username }}.</p>
    </div>
  </div>
@endsection
