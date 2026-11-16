@extends('layouts.admin')

@section('title', 'Settings: Traffic and earnings')

@section('content')
  <x-admin.settings-nav />

  <form action="{{ route('admin.settings.traffic.update') }}" method="post">
    @csrf
    @method('PUT')

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Views and earnings</h3></div>
      <div class="box-body">
        @php
          $fields = [
              ['dwell_seconds', 'Seconds on each step', 'How long a visitor waits on each step before moving on. Between 5 and 60.', 5, 60],
              ['unique_window_hours', 'Unique visitor window (hours)', 'A visitor is paid once per link in this window.', 1, 720],
              ['review_days', 'Review period (days)', 'Earnings stay pending this long before they are released.', 0, 60],
              ['referral_percent', 'Referral commission (%)', 'Share of a referred member\'s earnings paid to the member who invited them. Between 0 and 50.', 0, 50],
              ['session_minutes', 'Visit session length (minutes)', 'How long a visitor has to finish a link.', 5, 240],
              ['view_purge_days', 'Raw view retention (days)', 'Raw view rows older than this are purged. Daily totals are kept.', 7, 365],
              ['alias_length', 'Generated alias length', 'Number of characters in new short links. Between 6 and 12.', 6, 12],
          ];
        @endphp
        @foreach ($fields as [$name, $label, $help, $min, $max])
          <x-admin.setting-input :name="$name" :label="$label" :help="$help" type="number" :value="$values[$name]" min="{{ $min }}" max="{{ $max }}" step="1" required />
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
        <x-admin.setting-input name="code_session_seconds" label="Code session length (seconds)" help="How long a visitor has to find and enter the code. Between 120 and 3600." type="number" :value="$values['code_session_seconds']" min="120" max="3600" step="1" required />
        <x-admin.setting-input name="tutorial_video_url" label="Tutorial video link" help="Optional. A YouTube or Vimeo link over https that shows visitors how to find the code." type="url" :value="$values['tutorial_video_url']" maxlength="255" placeholder="https://www.youtube.com/watch?v=..." />
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Multiple accounts and sign-ups</h3></div>
      <div class="box-body">
        <x-admin.setting-input name="signups_per_ip_per_day" label="Sign-ups per IP address per day" help="How many new accounts one IP address may create in 24 hours. 0 turns the limit off. Sign-ins are never blocked." type="number" :value="$values['signups_per_ip_per_day']" min="0" max="1000" step="1" required />
        <x-admin.setting-input name="multi_account_shared_ip_limit" label="Shared network limit (accounts)" help="An IP address or browser used by more accounts than this is treated as a shared network (office, school, mobile carrier). Matches through it still show up under related accounts, but count for a quarter of their weight, so a large farm on one network stays visible at a low score. A referrer and the member they invited are always flagged when they share the sign-up IP and browser, whatever this limit. Between 2 and 1000." type="number" :value="$values['multi_account_shared_ip_limit']" min="2" max="1000" step="1" required />
        <x-admin.setting-input name="login_purge_days" label="Sign-in history retention (days)" help="Sign-up and sign-in records (IP address, browser, country) older than this are deleted every night. Between 7 and 365." type="number" :value="$values['login_purge_days']" min="7" max="365" step="1" required />
        <div @class(['form-group', 'has-error' => $errors->has('referral_hold_on_suspicion')])>
          <input type="hidden" name="referral_hold_on_suspicion" value="0">
          <div class="checkbox">
            <label><input type="checkbox" name="referral_hold_on_suspicion" value="1" @checked(old('referral_hold_on_suspicion', $values['referral_hold_on_suspicion']))> Hold suspicious referral commission</label>
          </div>
          <span class="help-block">@error('referral_hold_on_suspicion'){{ $message }}@else When a member and the person who invited them signed up from the same IP address and browser, a fraud flag is always raised. With this on, the referral commission for that pair is also kept on the flag until you pay it out or mark the flag handled. With it off, the commission is paid as usual.@enderror</span>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Advertiser pricing</h3></div>
      <div class="box-body">
        <x-admin.setting-input name="advertiser_default_price_usd" label="Default price per valid view (USD)" help="Charged to the advertiser unless the campaign has its own price. Keep it above the highest publisher payout." type="number" :value="$values['advertiser_default_price_usd']" min="0" step="0.0001" required />
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save settings</button>
      </div>
    </div>
  </form>
@endsection
