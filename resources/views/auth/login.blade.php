@extends('layouts.auth')

@section('title', 'Sign In')
@section('description', 'Sign in to start your session')

@section('content')
  <p class="login-box-msg">Sign in to start your session</p>

  <div id="flash">@include('auth.partials.status')</div>
  <form method="post" accept-charset="utf-8" id="signin-form" action="{{ route('login') }}" novalidate>
    @csrf
    <x-auth.input name="username" placeholder="Username or email address" maxlength="150" />
    <x-auth.input name="password" type="password" placeholder="Password" />
    <div class="form-group checkbox">
      <label for="remember-me"><input type="checkbox" name="remember_me" value="1" id="remember-me" @checked(old('remember_me'))>Remember me</label>
      <span class="help-block"></span>
    </div>

    <button class="btn btn-primary btn-block btn-flat" type="submit">Sign In</button>
  </form>
  <div class="social-auth-links text-center">
    <p>- OR -</p>
  </div>

  <a href="{{ route('password.request') }}">I forgot my password</a>
  <br>
  <a href="{{ route('register') }}" class="text-center">Register a new membership</a>
@endsection
