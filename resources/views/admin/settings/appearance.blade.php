@extends('layouts.admin')

@section('title', 'Settings: Appearance')

@section('content')
  <x-admin.settings-nav />

  <div class="callout callout-info">
    <p>Change the logo, favicon and brand colour of the public pages. Until you upload or choose something, the original LinkCash images and green are used. Images are saved where the File storage page says.</p>
  </div>

  @if ($serverLimitTooLow)
    <div class="callout callout-warning">
      <p>This server only accepts uploads up to {{ ini_get('upload_max_filesize') }}. Keep images below that size, or ask your host to raise <code>upload_max_filesize</code> and <code>post_max_size</code> to at least 6M.</p>
    </div>
  @endif

  <form action="{{ route('admin.settings.appearance.update') }}" method="post" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="box box-primary">
      <div class="box-header with-border"><h3 class="box-title">Brand colour</h3></div>
      <div class="box-body">
        <div @class(['form-group', 'has-error' => $errors->has('brand_color')])>
          <label for="brand_color">
            Main colour
            @if ($hasCustomColor)
              <span class="label label-success">Custom</span>
            @else
              <span class="label label-default">Default</span>
            @endif
          </label>
          <div>
            <input type="color" id="brand_color" name="brand_color" value="{{ old('brand_color', $brandColor) }}" style="width: 72px; height: 40px; padding: 2px; vertical-align: middle">
            <code style="margin-left: 8px">{{ $brandColor }}</code>
          </div>
          <span class="help-block">@error('brand_color'){{ $message }}@else Used for buttons, links, the header and the footer of the public pages and the visit pages. Lighter and darker shades are made from it automatically. The member, advertiser and admin areas keep their own colours.@enderror</span>
        </div>

        <p><strong>Shades made from the current colour</strong></p>
        <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 12px">
          @foreach ($palette as $shade => $color)
            <div style="width: 56px; padding: 18px 0 4px; border: 1px solid #ddd; border-radius: 3px; background: {{ $color }}; color: {{ $shade < 500 ? '#333' : '#fff' }}; font-size: 11px; text-align: center">{{ $shade }}</div>
          @endforeach
        </div>
        <p>
          <span style="display: inline-block; padding: 8px 16px; border-radius: 4px; background: {{ $palette[600] }}; color: {{ $textColor }}; font-weight: 600">Button preview</span>
          <span class="text-muted" style="margin-left: 8px">Button text is switched to dark automatically when white text would be hard to read.</span>
        </p>
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save settings</button>
        @if ($hasCustomColor)
          <button type="submit" form="reset-brand-color" class="btn btn-default">Reset to default ({{ $defaultColor }})</button>
        @endif
      </div>
    </div>

    @foreach ($assets as $asset)
      @php($uploadedUrl = \App\Services\Branding::uploadedImageUrl($asset))
      @php($currentUrl = \App\Services\Branding::imageUrl($asset))
      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">{{ $asset->label() }}</h3></div>
        <div class="box-body">
          <div style="display: inline-block; margin-bottom: 12px; padding: 12px; border: 1px solid #ddd; border-radius: 3px; background: {{ $asset === \App\Enums\BrandAsset::LogoDark ? $palette[900] : '#fff' }}">
            @if ($currentUrl)
              <img src="{{ $currentUrl }}" alt="Current {{ strtolower($asset->label()) }}" style="display: block; max-width: 320px; max-height: 120px">
            @else
              <span class="text-muted">No image uploaded.</span>
            @endif
          </div>
          <p>
            @if ($uploadedUrl)
              <span class="label label-success">Uploaded</span>
            @elseif ($asset->defaultImage())
              <span class="label label-default">Default image</span>
            @else
              <span class="label label-default">Not set</span>
            @endif
          </p>
          <div @class(['form-group', 'has-error' => $errors->has($asset->value)])>
            <label for="{{ $asset->value }}">Upload a new image</label>
            <input type="file" id="{{ $asset->value }}" name="{{ $asset->value }}" accept="image/jpeg,image/png,image/webp,image/gif">
            <span class="help-block">@error($asset->value){{ $message }}@else{{ $asset->help() }} JPG, PNG, WebP or GIF, up to 5 MB.@enderror</span>
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary">Save settings</button>
          @if ($uploadedUrl)
            <button type="submit" form="reset-{{ $asset->value }}" class="btn btn-default">{{ $asset->defaultImage() ? 'Reset to default' : 'Remove image' }}</button>
          @endif
        </div>
      </div>
    @endforeach
  </form>

  <form id="reset-brand-color" action="{{ route('admin.settings.appearance.color.reset') }}" method="post" hidden>
    @csrf
    @method('DELETE')
  </form>
  @foreach ($assets as $asset)
    <form id="reset-{{ $asset->value }}" action="{{ route('admin.settings.appearance.images.reset', $asset) }}" method="post" hidden>
      @csrf
      @method('DELETE')
    </form>
  @endforeach
@endsection
