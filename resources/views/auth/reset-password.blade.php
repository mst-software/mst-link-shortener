@extends('layouts.auth')

@section('title', 'Reset Password')
@section('description', 'Choose a new password for your account.')

@section('content')
  <div id="flash">@include('auth.partials.status')</div>
  <form method="post" accept-charset="utf-8" id="resetpassword-form" action="{{ route('password.update') }}" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <p>Choose a new password for your account.</p>

    <x-auth.input name="email" type="email" placeholder="Email" maxlength="254" :value="$email" />
    <x-auth.input name="password" type="password" placeholder="New password" toggle>
      <x-password-meter />
    </x-auth.input>

    <button class="btn btn-primary btn-block btn-flat" type="submit">Reset password</button>
  </form>
  <a href="{{ route('login') }}">Back to sign in</a>
@endsection
