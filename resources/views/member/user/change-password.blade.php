@extends('layouts.member')

@section('title', 'Change Password')

@section('breadcrumb')
  <li>Settings</li>
  <li class="active">Change Password</li>
@endsection

@section('content')
  <div class="box box-primary">
    <div class="box-body">
      <form method="post" action="{{ route('member.user.change-password.update') }}" novalidate>
        @csrf
        <div @class(['form-group', 'has-error' => $errors->has('current_password')])>
          <label for="current_password">Current password</label>
          <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Current password" autocomplete="current-password">
          <span class="help-block">{{ $errors->first('current_password') }}</span>
        </div>

        <div @class(['form-group', 'has-error' => $errors->has('password')])>
          <label for="password">New password</label>
          <div class="password-wrap">
            <input type="password" name="password" id="password" class="form-control" placeholder="New password" autocomplete="new-password">
            <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
              <i class="fa fa-eye pw-eye-on"></i>
              <i class="fa fa-eye-slash pw-eye-off"></i>
            </button>
          </div>
          <x-password-meter />
          <span class="help-block">{{ $errors->first('password') }}</span>
        </div>

        <button type="submit" class="btn btn-primary">Submit</button>
      </form>
    </div>
  </div>
@endsection

@section('scripts')
  @vite('resources/js/password-meter.js')
@endsection
