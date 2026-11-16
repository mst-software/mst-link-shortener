@extends('layouts.admin')

@section('title', 'Settings: Email')

@section('content')
  <x-admin.settings-nav />

  <div @class(['callout', 'callout-success' => $isConfigured, 'callout-info' => ! $isConfigured])>
    @if ($isConfigured)
      <p>The site sends emails with the settings below.</p>
    @else
      <p>These settings have not been saved yet, so the site uses the server defaults (currently: {{ $currentMailer }}). Save this page to take control of how emails are sent.</p>
    @endif
  </div>

  <x-admin.queue-restart-note />


  <form action="{{ route('admin.settings.email.update') }}" method="post">
    @csrf
    @method('PUT')

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Sending method</h3></div>
      <div class="box-body">
        <div class="form-group @error('mail_mailer') has-error @enderror">
          <label for="mail_mailer">How emails are sent</label>
          <select id="mail_mailer" name="mail_mailer" class="form-control" required>
            @foreach ($mailers as $value => $label)
              <option value="{{ $value }}" @selected(old('mail_mailer', $values['mail_mailer']) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          <span class="help-block">@error('mail_mailer'){{ $message }}@else Choose SMTP to deliver real emails (password resets, email verification, withdrawal updates). Your email provider gives you the SMTP details.@enderror</span>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">SMTP server</h3></div>
      <div class="box-body">
        <x-admin.setting-input name="mail_host" label="SMTP server" help="The address of your provider's mail server, for example smtp.gmail.com or smtp.sendgrid.net." :value="$values['mail_host']" maxlength="253" autocomplete="off" />
        <x-admin.setting-input name="mail_port" label="Port" help="Usually 587 for TLS, 465 for SSL or 25 without encryption." type="number" :value="$values['mail_port']" min="1" max="65535" step="1" />
        <div class="form-group @error('mail_encryption') has-error @enderror">
          <label for="mail_encryption">Encryption</label>
          <select id="mail_encryption" name="mail_encryption" class="form-control" required>
            @foreach ($encryptions as $value => $label)
              <option value="{{ $value }}" @selected(old('mail_encryption', $values['mail_encryption']) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          <span class="help-block">@error('mail_encryption'){{ $message }}@else Use what your provider recommends. TLS on port 587 works with most providers.@enderror</span>
        </div>
        <x-admin.setting-input name="mail_username" label="Username" help="Often your full email address. Leave blank if the server needs no login." :value="$values['mail_username']" maxlength="255" autocomplete="off" />
        <x-admin.secret-input name="mail_password" label="Password" help="The SMTP or app password from your provider." :saved="$hasPassword" :server-default="$hasServerPassword && ! $isConfigured" />
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Sender</h3></div>
      <div class="box-body">
        <x-admin.setting-input name="mail_from_address" label="Sender email address" help="Emails come from this address. Many providers only allow an address on your own domain." type="email" :value="$values['mail_from_address']" maxlength="254" required />
        <x-admin.setting-input name="mail_from_name" label="Sender name" help="The name people see in their inbox, usually the site name." :value="$values['mail_from_name']" maxlength="100" required />
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save settings</button>
      </div>
    </div>
  </form>

  <div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Send a test email</h3></div>
    <div class="box-body">
      <p>Save your changes first, then send a test email to <strong>{{ auth()->user()->email }}</strong>. If it fails, the error from the mail server is shown at the top of the page.</p>
      <form action="{{ route('admin.settings.email.test') }}" method="post">
        @csrf
        <button type="submit" class="btn btn-default"><i class="fa fa-paper-plane"></i> Send test email</button>
      </form>
    </div>
  </div>
@endsection
