@extends('layouts.auth')

@section('title', 'Forgot Password')
@section('description', 'Enter your e-mail address below to reset your password.')

@section('content')
  <h1 class="auth-title">Forgot your password?</h1>

  <div id="flash">@include('auth.partials.status')</div>
  <form method="post" accept-charset="utf-8" id="forgotpassword-form" action="{{ route('password.email') }}" novalidate>
    @csrf
    <p class="login-box-msg">Enter your e-mail address below to reset your password.</p>

    <x-auth.input name="email" type="email" label="Email" placeholder="you@example.com" maxlength="254" />

    <button class="btn btn-primary btn-block btn-flat" type="submit">Submit</button>
  </form>

  <p class="auth-switch">Remembered it? <a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
