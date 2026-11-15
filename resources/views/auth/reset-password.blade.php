@extends('layouts.auth')

@section('title', 'Reset Password')
@section('description', 'Choose a new password for your account.')

@section('content')
  <h1 class="auth-title">Reset your password</h1>

  <div id="flash">@include('auth.partials.status')</div>
  <form method="post" accept-charset="utf-8" id="resetpassword-form" action="{{ route('password.update') }}" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <p class="login-box-msg">Choose a new password for your account.</p>

    <x-auth.input name="email" type="email" label="Email" placeholder="you@example.com" maxlength="254" :value="$email" />
    <x-auth.input name="password" type="password" label="New password" placeholder="At least 8 characters" toggle>
      <x-password-meter />
    </x-auth.input>

    <button class="btn btn-primary btn-block btn-flat" type="submit">Reset password</button>
  </form>

  <p class="auth-switch"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
