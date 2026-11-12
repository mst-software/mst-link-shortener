@extends('layouts.auth')

@section('title', 'Sign Up')
@section('description', 'Register a new membership')

@section('content')
  <p class="login-box-msg">Register a new membership</p>

  <div id="flash"></div>
  <form method="post" accept-charset="utf-8" id="signup-form" action="{{ route('register') }}" novalidate>
    @csrf
    <x-auth.input name="username" placeholder="Username" maxlength="150" />
    <x-auth.input name="email" type="email" placeholder="Email" maxlength="254" />
    <x-auth.input name="password" type="password" placeholder="Password" toggle>
      <div class="pw-meter" aria-hidden="true"><i></i></div>
      <small class="pw-text" id="pw-text" aria-live="polite"></small>
      <ul class="pw-rules" id="pw-rules">
        <li data-rule="length">At least 8 characters</li>
        <li data-rule="case">Upper and lower case letters</li>
        <li data-rule="number">At least one number</li>
        <li data-rule="symbol">At least one special character</li>
      </ul>
    </x-auth.input>

    <div class="form-group">
      <div class="form-group checkbox required">
        <label for="accept"><input type="checkbox" name="accept" value="1" required="required" id="accept"><b>I agree to the <a href="{{ route('terms') }}" target="_blank">Terms of Use</a> and <a href="{{ route('privacy') }}" target="_blank">Privacy Policy</a>.</b></label>
        <span class="help-block"></span>
      </div>
    </div>

    <button class="btn btn-primary btn-block btn-flat" type="submit">Register</button>
  </form>
  <div class="social-auth-links text-center">
    <p>- OR -</p>
  </div>

  <a href="{{ route('login') }}" class="text-center">I already have a membership</a>
@endsection
