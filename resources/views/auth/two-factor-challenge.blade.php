@extends('layouts.auth')

@section('title', 'Two-Factor Authentication')
@section('description', 'Enter the code from your authenticator app')

@section('content')
  <p class="login-box-msg">Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>

  <form method="post" accept-charset="utf-8" id="two-factor-form" action="{{ route('two-factor.challenge') }}" novalidate>
    @csrf
    <x-auth.input name="code" placeholder="Authentication or recovery code" maxlength="20" value="" />

    <button class="btn btn-primary btn-block btn-flat" type="submit">Verify</button>
  </form>
  <br>
  <a href="{{ route('login') }}">Back to sign in</a>
@endsection
