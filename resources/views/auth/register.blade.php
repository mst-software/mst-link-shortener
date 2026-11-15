@extends('layouts.auth')

@section('title', 'Sign Up')
@section('description', 'Register a new membership')

@section('content')
  <h1 class="auth-title">Create your account</h1>
  <p class="login-box-msg">Register a new membership</p>

  <div id="flash">@include('auth.partials.status')</div>
  <form method="post" accept-charset="utf-8" id="signup-form" action="{{ route('register') }}" novalidate>
    @csrf
    <x-auth.input name="username" label="Username" placeholder="e.g. john_doe" maxlength="32" />
    <x-auth.input name="email" type="email" label="Email" placeholder="you@example.com" maxlength="254" />
    <x-auth.input name="password" type="password" label="Password" placeholder="At least 8 characters" toggle>
      <x-password-meter />
    </x-auth.input>

    <div @class(['form-group', 'checkbox', 'required', 'has-error' => $errors->has('accept')])>
      <label for="accept"><input type="checkbox" name="accept" value="1" required="required" id="accept" @checked(old('accept'))><span>I agree to the <a href="{{ route('terms') }}" target="_blank">Terms of Use</a> and <a href="{{ route('privacy') }}" target="_blank">Privacy Policy</a>.</span></label>
      <span class="help-block">{{ $errors->first('accept') }}</span>
    </div>

    <button class="btn btn-primary btn-block btn-flat" type="submit">Register</button>
  </form>

  <div class="auth-divider"><span>or</span></div>

  <p class="auth-switch">Already a member? <a href="{{ route('login') }}">I already have a membership</a></p>
@endsection
