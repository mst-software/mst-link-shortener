@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
  <form action="{{ route('admin.settings.update') }}" method="post">
    @csrf
    @method('PUT')

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Views and earnings</h3></div>
      <div class="box-body">
        @php
          $fields = [
              ['dwell_seconds', 'Seconds on each step', 'Between 5 and 60.', 5, 60],
              ['unique_window_hours', 'Unique visitor window (hours)', 'A visitor is paid once per link in this window.', 1, 720],
              ['review_days', 'Review period (days)', 'Earnings stay pending this long before they are released.', 0, 60],
              ['min_withdraw_usd', 'Minimum withdrawal (USD)', 'Between 1 and 10,000.', 1, 10000],
              ['referral_percent', 'Referral commission (%)', 'Between 0 and 50.', 0, 50],
              ['session_minutes', 'Visit session length (minutes)', 'How long a visitor has to finish a link.', 5, 240],
              ['view_purge_days', 'Raw view retention (days)', 'Raw view rows older than this are purged.', 7, 365],
              ['alias_length', 'Generated alias length', 'Between 6 and 12.', 6, 12],
          ];
        @endphp
        @foreach ($fields as [$name, $label, $help, $min, $max])
          <div class="form-group @error($name) has-error @enderror">
            <label for="{{ $name }}">{{ $label }}</label>
            <input type="number" id="{{ $name }}" name="{{ $name }}" class="form-control" min="{{ $min }}" max="{{ $max }}" step="{{ $name === 'min_withdraw_usd' ? '0.01' : '1' }}" required value="{{ old($name, $values[$name]) }}">
            <span class="help-block">@error($name){{ $message }}@else{{ $help }}@enderror</span>
          </div>
        @endforeach
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Keyword code</h3></div>
      <div class="box-body">
        <div class="form-group @error('visit_mode') has-error @enderror">
          <label for="visit_mode">Visit mode</label>
          <select id="visit_mode" name="visit_mode" class="form-control" required>
            @foreach (['auto' => 'Automatic (keyword when a campaign is available, otherwise wait)', 'keyword' => 'Keyword only', 'wait' => 'Wait only'] as $value => $label)
              <option value="{{ $value }}" @selected(old('visit_mode', $values['visit_mode']) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          <span class="help-block">@error('visit_mode'){{ $message }}@else Chooses how new visitors get past a link.@enderror</span>
        </div>
        <div class="form-group @error('code_session_seconds') has-error @enderror">
          <label for="code_session_seconds">Code session length (seconds)</label>
          <input type="number" id="code_session_seconds" name="code_session_seconds" class="form-control" min="120" max="3600" step="1" required value="{{ old('code_session_seconds', $values['code_session_seconds']) }}">
          <span class="help-block">@error('code_session_seconds'){{ $message }}@else How long a visitor has to find and enter the code. Between 120 and 3600.@enderror</span>
        </div>
        <div class="form-group @error('tutorial_video_url') has-error @enderror">
          <label for="tutorial_video_url">Tutorial video link</label>
          <input type="url" id="tutorial_video_url" name="tutorial_video_url" class="form-control" maxlength="255" placeholder="https://www.youtube.com/watch?v=..." value="{{ old('tutorial_video_url', $values['tutorial_video_url']) }}">
          <span class="help-block">@error('tutorial_video_url'){{ $message }}@else Optional. A YouTube or Vimeo link over https.@enderror</span>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Advertisers</h3></div>
      <div class="box-body">
        @php
          $advertiserFields = [
              ['advertiser_default_price_usd', 'Default price per valid view (USD)', 'number', '0.0001', 'Charged to the advertiser unless the campaign has its own price. Keep it above the highest publisher payout.'],
              ['advertiser_min_topup_usd', 'Minimum top up (USD)', 'number', '0.01', 'Applies to bank transfers and PayPal.'],
              ['vnd_per_usd', 'VND per 1 USD', 'number', '1', 'Used to convert bank transfers. Locked when a transfer is created.'],
              ['sepay_bank_name', 'Bank name', 'text', null, 'Shown on the QR code, for example MBBank.'],
              ['sepay_account_number', 'Account number', 'text', null, 'The account SePay watches for incoming transfers.'],
              ['sepay_account_name', 'Account holder name', 'text', null, 'Shown to advertisers next to the QR code.'],
          ];
        @endphp
        @foreach ($advertiserFields as [$name, $label, $type, $step, $help])
          <div class="form-group @error($name) has-error @enderror">
            <label for="{{ $name }}">{{ $label }}</label>
            <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" class="form-control" @if ($step) step="{{ $step }}" min="0" @endif value="{{ old($name, $values[$name]) }}">
            <span class="help-block">@error($name){{ $message }}@else{{ $help }}@enderror</span>
          </div>
        @endforeach
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Captcha</h3></div>
      <div class="box-body">
        <div class="form-group @error('captcha_provider') has-error @enderror">
          <label for="captcha_provider">Provider</label>
          <select id="captcha_provider" name="captcha_provider" class="form-control" required>
            @foreach ($providers as $provider)
              <option value="{{ $provider->value }}" @selected(old('captcha_provider', $currentProvider->value) === $provider->value)>{{ $provider->label() }}</option>
            @endforeach
          </select>
          @error('captcha_provider')<span class="help-block">{{ $message }}</span>@enderror
        </div>

        @foreach ($providers as $provider)
          <h4>{{ $provider->label() }}</h4>
          <div class="form-group @error($provider->value.'_site_key') has-error @enderror">
            <label for="{{ $provider->value }}_site_key">Site key</label>
            <input type="text" id="{{ $provider->value }}_site_key" name="{{ $provider->value }}_site_key" class="form-control" maxlength="255" autocomplete="off" value="{{ old($provider->value.'_site_key', $siteKeys[$provider->value]) }}">
            @error($provider->value.'_site_key')<span class="help-block">{{ $message }}</span>@enderror
          </div>
          <div class="form-group @error($provider->value.'_secret_key') has-error @enderror">
            <label for="{{ $provider->value }}_secret_key">Secret key</label>
            <input type="password" id="{{ $provider->value }}_secret_key" name="{{ $provider->value }}_secret_key" class="form-control" maxlength="255" autocomplete="new-password" value="" placeholder="{{ $hasSecret[$provider->value] ? 'Saved. Leave blank to keep it.' : 'Not set' }}">
            @error($provider->value.'_secret_key')<span class="help-block">{{ $message }}</span>@enderror
          </div>
        @endforeach
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save settings</button>
      </div>
    </div>
  </form>
@endsection
