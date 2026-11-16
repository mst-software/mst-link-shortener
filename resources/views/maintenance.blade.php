@extends('layouts.auth')

@section('title', 'Down for maintenance')
@section('description', config('app.name').' is down for maintenance')
@section('hide_notice', '1')

@section('content')
  <p class="login-box-msg"><strong>Down for maintenance</strong></p>
  <p>{{ \App\Http\Middleware\CheckMaintenanceMode::MESSAGE }}</p>
  @if ($notice !== '')
    <div class="alert alert-warning" style="white-space: pre-line">{{ $notice }}</div>
  @endif
  <p class="text-center"><a href="{{ route('login') }}">Admin sign in</a></p>
@endsection
