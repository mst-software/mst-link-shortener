@extends('layouts.auth')

@section('title', 'Verify Email')
@section('description', 'Verify your email address to finish setting up your account.')

@section('content')
  <p class="login-box-msg">Verify your email address</p>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click the link in that email to verify your address.</p>
  <p>Until your email is verified you cannot request withdrawals or change your password. If the address above is wrong, you can change it in your account settings.</p>

  <form method="post" action="{{ route('verification.send') }}">
    @csrf
    <button class="btn btn-primary btn-block btn-flat" type="submit">Resend verification email</button>
  </form>

  <div class="social-auth-links text-center">
    <p>- OR -</p>
  </div>

  <a href="{{ auth()->user()->dashboardUrl() }}">Continue to your dashboard</a>
@endsection
