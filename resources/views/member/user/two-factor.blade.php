@extends('layouts.member')

@section('title', 'Two-Factor Authentication')

@section('breadcrumb')
  <li>Settings</li>
  <li class="active">Two-Factor Authentication</li>
@endsection

@section('content')
  @if ($recoveryCodes)
    <div class="box box-warning">
      <div class="box-header with-border">
        <h3 class="box-title">Your recovery codes</h3>
      </div>
      <div class="box-body">
        <p>Store these codes somewhere safe. Each code signs you in once if you lose your phone. They are shown only now.</p>
        <ul class="list-unstyled" style="font-family: monospace; font-size: 16px; columns: 2; column-gap: 24px;">
          @foreach ($recoveryCodes as $recoveryCode)
            <li>{{ $recoveryCode }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  <div class="box box-primary">
    <div class="box-body">
      @if ($isEnabled)
        <p><span class="label label-success">On</span> Two-factor authentication is on. You need a code from your authenticator app to sign in.</p>
      @elseif ($isPending)
        <p>Add this account to an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, ...), then enter the 6-digit code it shows.</p>

        <div class="form-group">
          <label for="two-factor-secret">Setup key</label>
          <input type="text" id="two-factor-secret" class="form-control" value="{{ trim(chunk_split($secret, 4, ' ')) }}" readonly style="font-family: monospace;">
          <span class="help-block">On a phone, you can also <a href="{{ $provisioningUri }}">open this link in your authenticator app</a>.</span>
        </div>

        <form method="post" action="{{ route('member.user.two-factor.confirm') }}" novalidate>
          @csrf
          <div @class(['form-group', 'has-error' => $errors->has('code')])>
            <label for="code">Authentication code</label>
            <input type="text" name="code" id="code" class="form-control" placeholder="123456" inputmode="numeric" autocomplete="one-time-code" maxlength="20">
            <span class="help-block">{{ $errors->first('code') }}</span>
          </div>
          <button type="submit" class="btn btn-primary">Turn on</button>
        </form>
      @else
        <p><span class="label label-default">Off</span> Protect your account and your withdrawals with a code from an authenticator app on your phone.</p>
        <form method="post" action="{{ route('member.user.two-factor.store') }}">
          @csrf
          <button type="submit" class="btn btn-primary">Set up two-factor authentication</button>
        </form>
      @endif
    </div>
  </div>

  @if ($isEnabled)
    <div class="box box-default">
      <div class="box-header with-border">
        <h3 class="box-title">Recovery codes or turn off</h3>
      </div>
      <div class="box-body">
        <p>Confirm with your password and a code from your authenticator app (or a recovery code).</p>
        <form method="post" action="{{ route('member.user.two-factor.recovery-codes') }}" id="two-factor-manage" novalidate>
          @csrf
          <div @class(['form-group', 'has-error' => $errors->has('current_password')])>
            <label for="current_password">Current password</label>
            <input type="password" name="current_password" id="current_password" class="form-control" placeholder="Current password" autocomplete="current-password">
            <span class="help-block">{{ $errors->first('current_password') }}</span>
          </div>
          <div @class(['form-group', 'has-error' => $errors->has('code')])>
            <label for="code">Authentication code</label>
            <input type="text" name="code" id="code" class="form-control" placeholder="123456" autocomplete="one-time-code" maxlength="20">
            <span class="help-block">{{ $errors->first('code') }}</span>
          </div>
          <button type="submit" class="btn btn-default">New recovery codes</button>
          <button type="submit" class="btn btn-danger" formaction="{{ route('member.user.two-factor.destroy') }}" name="_method" value="DELETE">Turn off</button>
        </form>
      </div>
    </div>
  @endif
@endsection
