@extends('layouts.admin')

@section('title', 'Settings: Payments')

@section('content')
  <x-admin.settings-nav />

  <x-admin.queue-restart-note />

  <form action="{{ route('admin.settings.payments.update') }}" method="post">
    @csrf
    @method('PUT')

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Member withdrawals</h3></div>
      <div class="box-body">
        <x-admin.setting-input name="min_withdraw_usd" label="Minimum withdrawal (USD)" help="Members can only ask for a payout once their balance reaches this amount. Between 1 and 10,000." type="number" :value="$values['min_withdraw_usd']" min="1" max="10000" step="0.01" required />

        @php($checkedMethods = old('payout_methods', array_map(fn ($method) => $method->value, $enabledMethods)))
        <div @class(['form-group', 'has-error' => $errors->has('payout_methods') || $errors->has('payout_methods.*')])>
          <label>Payout methods offered to members</label>
          @foreach ($payoutMethods as $method)
            <div class="checkbox">
              <label>
                <input type="checkbox" name="payout_methods[]" value="{{ $method->value }}" @checked(in_array($method->value, (array) $checkedMethods, true))>
                {{ $method->label() }}
              </label>
            </div>
          @endforeach
          <span class="help-block">@if ($errors->has('payout_methods')){{ $errors->first('payout_methods') }}@elseif ($errors->has('payout_methods.*')){{ $errors->first('payout_methods.*') }}@else Members only see the ticked methods in their profile. Keep at least one ticked.@endif</span>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Advertiser top ups</h3></div>
      <div class="box-body">
        <x-admin.setting-input name="advertiser_min_topup_usd" label="Minimum top up (USD)" help="The smallest amount an advertiser can add to their wallet. Applies to bank transfers and PayPal." type="number" :value="$values['advertiser_min_topup_usd']" min="1" max="10000" step="0.01" required />
        <x-admin.setting-input name="vnd_per_usd" label="VND per 1 USD" help="Used to convert bank transfers in VND to USD. Each transfer keeps the rate from when it was created." type="number" :value="$values['vnd_per_usd']" min="1" max="1000000" step="1" required />
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">PayPal</h3></div>
      <div class="box-body">
        <p class="text-muted">Lets advertisers top up with PayPal. Create a REST app in the PayPal developer dashboard and copy its keys here.</p>
        <x-admin.setting-input name="paypal_client_id" label="Client ID" help="Leave blank to use the server default." :value="$values['paypal_client_id']" maxlength="255" autocomplete="off" />
        <x-admin.secret-input name="paypal_secret" label="Secret" help="The secret of the same PayPal app." :saved="$savedSecrets['paypal_secret']" :server-default="$serverDefaults['paypal_secret']" />
        <div class="form-group @error('paypal_mode') has-error @enderror">
          <label for="paypal_mode">Mode</label>
          <select id="paypal_mode" name="paypal_mode" class="form-control" required>
            <option value="sandbox" @selected(old('paypal_mode', $values['paypal_mode']) === 'sandbox')>Sandbox (test payments, no real money)</option>
            <option value="live" @selected(old('paypal_mode', $values['paypal_mode']) === 'live')>Live (real payments)</option>
          </select>
          <span class="help-block">@error('paypal_mode'){{ $message }}@else Use Sandbox keys with Sandbox mode and Live keys with Live mode.@enderror</span>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Bank transfer (SePay)</h3></div>
      <div class="box-body">
        <p class="text-muted">SePay watches the bank account below and credits advertiser wallets when a transfer arrives.</p>
        <x-admin.secret-input name="sepay_api_token" label="API token" help="From the SePay dashboard, under API access." :saved="$savedSecrets['sepay_api_token']" :server-default="$serverDefaults['sepay_api_token']" />
        <x-admin.setting-input name="sepay_base_url" label="API address" help="Leave blank to use the standard SePay address (https://my.sepay.vn)." type="url" :value="$values['sepay_base_url']" maxlength="255" placeholder="https://my.sepay.vn" />
        <x-admin.setting-input name="sepay_bank_name" label="Bank name" help="Shown on the QR code, for example MBBank." :value="$values['sepay_bank_name']" maxlength="100" />
        <x-admin.setting-input name="sepay_account_number" label="Account number" help="The account SePay watches for incoming transfers." :value="$values['sepay_account_number']" maxlength="50" />
        <x-admin.setting-input name="sepay_account_name" label="Account holder name" help="Shown to advertisers next to the QR code." :value="$values['sepay_account_name']" maxlength="100" />
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save settings</button>
      </div>
    </div>
  </form>
@endsection
