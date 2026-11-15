@extends('layouts.advertiser')

@section('title', 'Profile')

@section('content')
  <div class="row">
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Account</h3></div>
        <div class="box-body">
          <table class="table table-condensed">
            <tr><th style="width: 30%">Username</th><td>{{ auth()->user()->username }}</td></tr>
            <tr><th>Email</th><td>{{ auth()->user()->email }}</td></tr>
          </table>
        </div>
      </div>

      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Change email</h3></div>
        <form method="post" action="{{ route('advertiser.profile.email') }}" novalidate>
          @csrf
          <div class="box-body">
            <div @class(['form-group', 'has-error' => $errors->has('email')])>
              <label for="email">New email</label>
              <input type="email" name="email" id="email" class="form-control" maxlength="254" value="{{ old('email') }}">
              <span class="help-block">{{ $errors->first('email') }}</span>
            </div>
            <div @class(['form-group', 'has-error' => $errors->has('current_password') && old('email')])>
              <label for="email-current-password">Current password</label>
              <input type="password" name="current_password" id="email-current-password" class="form-control" autocomplete="current-password">
              <span class="help-block">{{ old('email') ? $errors->first('current_password') : '' }}</span>
            </div>
          </div>
          <div class="box-footer"><button type="submit" class="btn btn-primary">Change email</button></div>
        </form>
      </div>
    </div>

    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Change password</h3></div>
        <form method="post" action="{{ route('advertiser.profile.password') }}" novalidate>
          @csrf
          <div class="box-body">
            <div @class(['form-group', 'has-error' => $errors->has('current_password') && ! old('email')])>
              <label for="current_password">Current password</label>
              <input type="password" name="current_password" id="current_password" class="form-control" autocomplete="current-password">
              <span class="help-block">{{ old('email') ? '' : $errors->first('current_password') }}</span>
            </div>
            <div @class(['form-group', 'has-error' => $errors->has('password')])>
              <label for="password">New password</label>
              <input type="password" name="password" id="password" class="form-control" autocomplete="new-password">
              <span class="help-block">{{ $errors->first('password') }}</span>
            </div>
          </div>
          <div class="box-footer"><button type="submit" class="btn btn-primary">Change password</button></div>
        </form>
      </div>
    </div>
  </div>
@endsection
