@extends('layouts.auth')

@section('title', 'Sign In')
@section('description', 'Sign in to start your session')

@section('content')
  <h1 class="auth-title">Welcome back</h1>
  <p class="login-box-msg">Sign in to start your session</p>

  <div id="flash">@include('auth.partials.status')</div>
  <form method="post" accept-charset="utf-8" id="signin-form" action="{{ route('login') }}" novalidate>
    @csrf
    <x-auth.input name="username" label="Username or email" placeholder="Username or email address" maxlength="150" />
    <x-auth.input name="password" type="password" label="Password" placeholder="Your password" />
    <div class="form-group checkbox">
      <label for="remember-me"><input type="checkbox" name="remember_me" value="1" id="remember-me" @checked(old('remember_me'))>Remember me</label>
      <a href="{{ route('password.request') }}" class="auth-inline-link">I forgot my password</a>
      <span class="help-block"></span>
    </div>

    <button class="btn btn-primary btn-block btn-flat" type="submit">Sign In</button>
  </form>

  <div class="auth-divider"><span>or</span></div>

  <p class="auth-switch">New to LinkCash? <a href="{{ route('register') }}">Register a new membership</a></p>
@endsection
