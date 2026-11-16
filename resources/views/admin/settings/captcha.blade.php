@extends('layouts.admin')

@section('title', 'Settings: Captcha')

@section('content')
  <x-admin.settings-nav />

  <form action="{{ route('admin.settings.captcha.update') }}" method="post">
    @csrf
    @method('PUT')

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Captcha provider</h3></div>
      <div class="box-body">
        <div class="form-group @error('captcha_provider') has-error @enderror">
          <label for="captcha_provider">Provider</label>
          <select id="captcha_provider" name="captcha_provider" class="form-control" required>
            @foreach ($providers as $provider)
              <option value="{{ $provider->value }}" @selected(old('captcha_provider', $currentProvider->value) === $provider->value)>{{ $provider->label() }}</option>
            @endforeach
          </select>
          <span class="help-block">@error('captcha_provider'){{ $message }}@else The check visitors solve on the last step of a link. Fill in the keys of the provider you choose below.@enderror</span>
        </div>
      </div>
    </div>

    @foreach ($providers as $provider)
      <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">{{ $provider->label() }}</h3></div>
        <div class="box-body">
          <x-admin.setting-input :name="$provider->value.'_site_key'" label="Site key" help="The public key from the provider's dashboard. Leave blank to use the server default." :value="$siteKeys[$provider->value]" maxlength="255" autocomplete="off" />
          <x-admin.secret-input :name="$provider->value.'_secret_key'" label="Secret key" help="The private key from the provider's dashboard." :saved="$hasSecret[$provider->value]" />
        </div>
      </div>
    @endforeach

    <div class="box box-solid">
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save settings</button>
      </div>
    </div>
  </form>
@endsection
