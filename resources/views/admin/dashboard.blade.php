@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
  <div class="row">
    <div class="col-md-3 col-sm-6">
      <div class="info-box">
        <span class="info-box-icon bg-purple"><i class="fa fa-users"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Users</span>
          <span class="info-box-number">{{ number_format($userCount) }}</span>
        </div>
      </div>
    </div>
  </div>
@endsection
