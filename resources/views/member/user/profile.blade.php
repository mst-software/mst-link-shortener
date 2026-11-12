@extends('layouts.member')

@section('title', 'Profile')

@section('breadcrumb')
  <li>Settings</li>
  <li class="active">Profile</li>
@endsection

@php
  $member = auth()->user();
  $selectedMethod = old('payout_method', $member->payout_method?->value);
  $details = $member->payout_details ?? [];
  $detailFields = [
      'paypal' => [
          'paypal_email' => ['PayPal email', 'email'],
      ],
      'bank_transfer' => [
          'bank_name' => ['Bank name', 'text'],
          'account_number' => ['Account number', 'text'],
          'account_holder' => ['Account holder', 'text'],
          'branch' => ['Branch', 'text'],
          'bank_phone_number' => ['Contact phone number', 'text'],
      ],
      'momo' => [
          'momo_phone' => ['Momo phone number', 'text'],
      ],
      'usdt' => [
          'usdt_address' => ['USDT wallet address', 'text'],
      ],
  ];
  $billingFields = [
      'fullname' => ['Your name', 'Your name'],
      'phone_number' => ['Phone', 'Phone'],
      'address' => ['Address', 'Address'],
      'district' => ['District', 'District'],
      'province' => ['Province', 'Province'],
  ];
@endphp

@section('content')
  <div class="box box-primary">
    <div class="box-body">
      <form method="post" action="{{ route('member.user.profile.update') }}" id="profile-form" novalidate>
        @csrf

        <legend>Billing address</legend>
        <div class="row">
          @foreach ($billingFields as $field => [$label, $placeholder])
            <div class="col-md-6">
              <div @class(['form-group', 'has-error' => $errors->has($field)])>
                <label for="{{ $field }}">{{ $label }}</label>
                <input type="text" name="{{ $field }}" id="{{ $field }}" class="form-control" placeholder="{{ $placeholder }}" value="{{ old($field, $member->{$field}) }}">
                <span class="help-block">{{ $errors->first($field) }}</span>
              </div>
            </div>
          @endforeach
          <div class="col-md-6">
            <div @class(['form-group', 'has-error' => $errors->has('country')])>
              <label for="country">Country</label>
              <select name="country" id="country" class="form-control">
                <option value="">Select</option>
                @foreach ($countries as $code => $name)
                  <option value="{{ $code }}" @selected(old('country', $member->country) === $code)>{{ $name }}</option>
                @endforeach
              </select>
              <span class="help-block">{{ $errors->first('country') }}</span>
            </div>
          </div>
        </div>

        <legend>Link settings</legend>
        <div class="row">
          <div class="col-md-6">
            <div @class(['form-group', 'has-error' => $errors->has('default_level')])>
              <label for="default_level">Default level</label>
              <select name="default_level" id="default_level" class="form-control">
                @foreach ([1, 2, 3] as $level)
                  <option value="{{ $level }}" @selected((int) old('default_level', $member->default_level) === $level)>Level {{ $level }}</option>
                @endforeach
              </select>
              <span class="help-block">{{ $errors->first('default_level') ?: 'Used when you create a link without choosing a level.' }}</span>
            </div>
          </div>
        </div>

        <legend>Withdrawal info</legend>
        <div class="row">
          <div class="col-md-6">
            <div @class(['form-group', 'has-error' => $errors->has('traffic_source')])>
              <label for="traffic_source">Traffic source</label>
              <textarea name="traffic_source" id="traffic_source" class="form-control" rows="5" placeholder="Traffic source">{{ old('traffic_source', $member->traffic_source) }}</textarea>
              <span class="help-block">{{ $errors->first('traffic_source') }}</span>
            </div>

            <div @class(['form-group', 'has-error' => $errors->has('payout_method')])>
              <label>Payment method</label>
              <div>
                @foreach ($payoutMethods as $method)
                  <label class="radio-inline">
                    <input type="radio" name="payout_method" value="{{ $method->value }}" @checked($selectedMethod === $method->value)> {{ $method->label() }}
                  </label>
                @endforeach
              </div>
              <span class="help-block">{{ $errors->first('payout_method') }}</span>
            </div>

            @foreach ($detailFields as $method => $fields)
              <div class="payout-details" data-method="{{ $method }}" @if ($selectedMethod !== $method) style="display: none" @endif>
                @foreach ($fields as $field => [$label, $type])
                  <div @class(['form-group', 'has-error' => $errors->has($field)])>
                    <label for="detail_{{ $field }}">{{ $label }}</label>
                    <input type="{{ $type }}" name="{{ $field }}" id="detail_{{ $field }}" class="form-control" placeholder="{{ $label }}" value="{{ old($field, $details[$field === 'bank_phone_number' ? 'phone_number' : $field] ?? '') }}">
                    <span class="help-block">{{ $errors->first($field) }}</span>
                  </div>
                @endforeach
              </div>
            @endforeach
          </div>

          <div class="col-md-6">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Payment method</th>
                  <th>Minimum withdrawal amount</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($payoutMethods as $method)
                  <tr>
                    <td>{{ $method->label() }}</td>
                    <td>{{ $minimumWithdrawal }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>

            <p class="text-muted">- To get paid through PayPal, enter the email address of your PayPal account.</p>
            <p class="text-muted">- To get paid in USDT, enter your wallet address.</p>
            <p class="text-muted">- To get paid through Momo, enter your Momo phone number.</p>
            <p class="text-muted">- To get paid by bank transfer, enter your account number, account holder, bank name, branch and contact phone number.</p>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">Submit</button>
      </form>
    </div>
  </div>
@endsection

@section('scripts')
  @vite('resources/js/member-settings.js')
@endsection
