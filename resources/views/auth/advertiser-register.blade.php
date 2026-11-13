@extends('layouts.auth')

@section('title', 'Advertiser Sign Up')
@section('description', 'Register an advertiser account')

@section('content')
  <p class="login-box-msg">Register an advertiser account</p>

  <div id="flash">@include('auth.partials.status')</div>
  <form method="post" accept-charset="utf-8" id="signup-form" action="{{ route('advertiser.register') }}" novalidate>
    @csrf
    <x-auth.input name="username" placeholder="Username" maxlength="32" />
    <x-auth.input name="email" type="email" placeholder="Email" maxlength="254" />
    <x-auth.input name="password" type="password" placeholder="Password" toggle>
      <x-password-meter />
    </x-auth.input>

    <div class="form-group">
      <div @class(['form-group', 'checkbox', 'required', 'has-error' => $errors->has('accept')])>
        <label for="accept"><input type="checkbox" name="accept" value="1" required="required" id="accept" @checked(old('accept'))><b>I agree to the <a href="{{ route('terms') }}" target="_blank">Terms of Use</a> and <a href="{{ route('privacy') }}" target="_blank">Privacy Policy</a>, and I confirm my website has no gambling or 18+ content.</b></label>
        <span class="help-block">{{ $errors->first('accept') }}</span>
      </div>
    </div>

    <button class="btn btn-primary btn-block btn-flat" type="submit">Register as advertiser</button>
  </form>
  <div class="social-auth-links text-center">
    <p>- OR -</p>
  </div>

  <a href="{{ route('login') }}" class="text-center">I already have an account</a>
@endsection
