@extends('layouts.admin')

@section('title', 'Settings: File storage')

@section('content')
  <x-admin.settings-nav />

  <div class="callout callout-info">
    <p>Choose where uploaded images (logos, favicon, campaign images) are saved. Switching only affects new uploads: files uploaded earlier keep working from where they were saved.</p>
  </div>

  @if ($values['upload_disk'] === 'public' && ! $publicLinkExists)
    <div class="callout callout-warning">
      <p>Uploaded files cannot be shown yet because the public storage folder is not linked. Ask your developer or host to run <code>php artisan storage:link</code> once on the server.</p>
    </div>
  @endif

  <form action="{{ route('admin.settings.storage.update') }}" method="post">
    @csrf
    @method('PUT')

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Storage location</h3></div>
      <div class="box-body">
        <div class="form-group @error('upload_disk') has-error @enderror">
          <label for="upload_disk">Save uploaded files on</label>
          <select id="upload_disk" name="upload_disk" class="form-control" required>
            @foreach ($disks as $value => $label)
              <option value="{{ $value }}" @selected(old('upload_disk', $values['upload_disk']) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          <span class="help-block">@error('upload_disk'){{ $message }}@else This server is the simplest choice. Use S3-compatible storage (e.g. Cloudfly) to keep files off the hosting account or serve them from a CDN.@enderror</span>
        </div>
      </div>
    </div>

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">S3-compatible storage</h3></div>
      <div class="box-body">
        <p class="text-muted">Only needed when files are saved on S3-compatible storage such as Cloudfly. Your storage provider gives you these details in its S3 API or access key section. The bucket must allow public reads (or use a public address below), because visitors load the images directly.</p>
        <p class="text-muted">A field left empty keeps the value set on the server (in the .env file), if there is one.</p>
        <x-admin.setting-input name="s3_key" label="Access key ID" help="The ID of an access key that may read and write this bucket." :value="$values['s3_key']" maxlength="255" autocomplete="off" />
        <x-admin.secret-input name="s3_secret" label="Secret access key" help="The secret that belongs to the access key." :saved="$hasSecret" :server-default="$hasServerSecret && ! $hasSecret" />
        <x-admin.setting-input name="s3_region" label="Region" help='The region your provider names for the bucket, for example us-east-1. Use "auto" for Cloudflare R2; if your provider names no region, us-east-1 usually works.' :value="$values['s3_region']" maxlength="32" autocomplete="off" />
        <x-admin.setting-input name="s3_bucket" label="Bucket" help="The name of the bucket the files go in." :value="$values['s3_bucket']" maxlength="63" autocomplete="off" />
        <x-admin.setting-input name="s3_endpoint" label="Endpoint (optional)" help="Leave blank for Amazon S3. For Cloudfly, Cloudflare R2 and other S3-compatible providers, enter the S3 API address they give you, starting with https://." type="url" :value="$values['s3_endpoint']" maxlength="255" placeholder="https://" />
        <x-admin.setting-input name="s3_url" label="Public address (optional)" help="The address files are shown from, such as a CDN or the public bucket address (for example https://cdn.example.com). Leave blank to use the bucket address." type="url" :value="$values['s3_url']" maxlength="255" placeholder="https://" />
        <div @class(['form-group', 'has-error' => $errors->has('s3_use_path_style')])>
          <input type="hidden" name="s3_use_path_style" value="0">
          <div class="checkbox">
            <label><input type="checkbox" name="s3_use_path_style" value="1" @checked(old('s3_use_path_style', $values['s3_use_path_style']))> Use path-style addresses</label>
          </div>
          <span class="help-block">@error('s3_use_path_style'){{ $message }}@else Turn it on when your provider asks for path-style requests (bucket in the path, not in the host name), or when the connection test fails to find the bucket. Leave it off for Amazon S3 and Cloudflare R2.@enderror</span>
        </div>
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save settings</button>
      </div>
    </div>
  </form>

  <div class="box box-default">
    <div class="box-header with-border"><h3 class="box-title">Test the S3 connection</h3></div>
    <div class="box-body">
      <p>Save your changes first. The test writes a small file to the bucket, reads it back and deletes it. If it fails, the error from the storage service is shown at the top of the page.</p>
      <form action="{{ route('admin.settings.storage.test') }}" method="post">
        @csrf
        <button type="submit" class="btn btn-default"><i class="fa fa-plug"></i> Test connection</button>
      </form>
    </div>
  </div>
@endsection
