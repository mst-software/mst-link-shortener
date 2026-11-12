@extends('layouts.member')

@section('title', 'Change Email')

@section('breadcrumb')
  <li>Settings</li>
  <li class="active">Change Email</li>
@endsection

@section('content')
  <div class="box box-primary">
    <div class="box-body">
      <form method="post" action="{{ route('member.user.change-email.update') }}" novalidate>
        @csrf
        <div class="form-group">
          <label for="current_email">Current email</label>
          <input type="text" id="current_email" class="form-control" value="{{ auth()->user()->email }}" readonly>
        </div>

        <div @class(['form-group', 'has-error' => $errors->has('email')])>
          <label for="email">New email</label>
          <input type="email" name="email" id="email" class="form-control" placeholder="New email" value="{{ old('email') }}" autocomplete="email">
          <span class="help-block">{{ $errors->first('email') }}</span>
        </div>

        <div @class(['form-group', 'has-error' => $errors->has('current_password')])>
          <label for="current_password">Current password</label>
          <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Current password" autocomplete="current-password">
          <span class="help-block">{{ $errors->first('current_password') }}</span>
        </div>

        <button type="submit" class="btn btn-primary">Submit</button>
      </form>
    </div>
  </div>
@endsection
