@extends('layouts.auth')

@section('title', 'Forgot Password')
@section('description', 'Enter your e-mail address below to reset your password.')

@section('content')
  <div id="flash">@include('auth.partials.status')</div>
  <form method="post" accept-charset="utf-8" id="forgotpassword-form" action="{{ route('password.email') }}" novalidate>
    @csrf
    <p>Enter your e-mail address below to reset your password.</p>

    <x-auth.input name="email" type="email" placeholder="Email" maxlength="254" />

    <button class="btn btn-primary btn-block btn-flat" type="submit">Submit</button>
  </form>
@endsection
