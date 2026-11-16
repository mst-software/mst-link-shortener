@extends('layouts.admin')

@section('title', 'Settings: General')

@section('content')
  <x-admin.settings-nav />

  <form action="{{ route('admin.settings.general.update') }}" method="post">
    @csrf
    @method('PUT')

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Site</h3></div>
      <div class="box-body">
        <x-admin.setting-input name="site_name" label="Site name" help="Shown in page titles, the footer, emails and the legal pages. The logo image does not change." :value="$values['site_name']" maxlength="60" required />
        <x-admin.setting-input name="support_email" label="Support email" help="Optional. Shown in the footer and on the Terms of Use and Privacy Policy pages so people can contact you." type="email" :value="$values['support_email']" maxlength="254" placeholder="support@example.com" />
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Social and contact links</h3></div>
      <div class="box-body">
        <p class="text-muted">Optional. Only the links you fill in are shown in the footer of the public pages.</p>
        @foreach ($socialNetworks as $network)
          <x-admin.setting-input :name="$network->settingKey()" :label="$network->label()" :help="'For example '.$network->example()" type="url" :value="$values[$network->settingKey()]" maxlength="255" :placeholder="$network->example()" />
        @endforeach
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Sign-ups</h3></div>
      <div class="box-body">
        <div @class(['form-group', 'has-error' => $errors->has('registration_open')])>
          <input type="hidden" name="registration_open" value="0">
          <div class="checkbox">
            <label><input type="checkbox" name="registration_open" value="1" @checked(old('registration_open', $values['registration_open']))> Allow new members and advertisers to sign up</label>
          </div>
          <span class="help-block">@error('registration_open'){{ $message }}@else When this is off, the sign-up pages show a "sign-ups are closed" message. People who already have an account can still sign in.@enderror</span>
        </div>
      </div>
    </div>

    <div class="box box-warning">
      <div class="box-header with-border"><h3 class="box-title">Maintenance and notices</h3></div>
      <div class="box-body">
        <div @class(['form-group', 'has-error' => $errors->has('maintenance_mode')])>
          <input type="hidden" name="maintenance_mode" value="0">
          <div class="checkbox">
            <label><input type="checkbox" name="maintenance_mode" value="1" @checked(old('maintenance_mode', $values['maintenance_mode']))> Maintenance mode</label>
          </div>
          <span class="help-block">@error('maintenance_mode'){{ $message }}@else While this is on, visitors and members see a "we will be back soon" page and short links do not open. Admins can still sign in and use the site. Turn it off when you are done.@enderror</span>
        </div>
        <div @class(['form-group', 'has-error' => $errors->has('maintenance_message')])>
          <label for="maintenance_message">Notice message</label>
          <textarea id="maintenance_message" name="maintenance_message" class="form-control" rows="3" maxlength="500">{{ old('maintenance_message', $values['maintenance_message']) }}</textarea>
          <span class="help-block">@error('maintenance_message'){{ $message }}@else Optional, up to 500 characters. Shown as a banner at the top of the public pages, for example "Payouts are delayed until Monday". In maintenance mode it is shown on the maintenance page. Leave blank to show nothing.@enderror</span>
        </div>
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save settings</button>
      </div>
    </div>
  </form>
@endsection
