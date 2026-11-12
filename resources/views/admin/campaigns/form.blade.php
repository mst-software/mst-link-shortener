@extends('layouts.admin')

@section('title', $campaign->exists ? 'Edit campaign' : 'New campaign')

@section('content')
  @if ($campaign->exists)
    <div class="box box-info">
      <div class="box-header with-border"><h3 class="box-title">Embed code</h3></div>
      <div class="box-body">
        <p class="text-muted">Give this snippet to the advertiser. It goes on every page that should show the keyword widget.</p>
        <textarea id="embed-code" class="form-control" rows="2" readonly style="font-family: monospace">&lt;script async src="{{ url('/js/widget.js') }}" data-campaign="{{ $campaign->widget_key }}"&gt;&lt;/script&gt;</textarea>
        <button type="button" id="copy-embed" class="btn btn-default btn-sm" style="margin-top: 8px"><i class="fa fa-copy"></i> Copy</button>
      </div>
    </div>
  @endif

  <div class="box box-primary">
    <form action="{{ $campaign->exists ? route('admin.campaigns.update', $campaign) : route('admin.campaigns.store') }}" method="post" enctype="multipart/form-data">
      @csrf
      @if ($campaign->exists)
        @method('PUT')
      @endif
      <div class="box-body">
        @php
          $textFields = [
              ['name', 'Name', 'text', 'For your own reference.'],
              ['keyword', 'Keyword', 'text', 'The phrase the visitor searches for on Google.'],
              ['domain', 'Domain', 'text', 'The advertiser site, for example example-advertiser.com. Widget requests are only accepted from this domain and its subdomains.'],
              ['display_domain', 'Display domain', 'text', 'Shown to visitors with some characters hidden. Leave blank to fill it in automatically.'],
          ];
          $numberFields = [
              ['weight', 'Weight', 1, 1000, 'Higher weight is picked more often.'],
              ['daily_limit', 'Daily limit', 0, 1000000, 'Codes issued per day. 0 means no limit.'],
              ['stage1_seconds', 'Stage 1 seconds', 5, 600, 'How long the visitor must stay on the first page.'],
              ['stage2_seconds', 'Stage 2 seconds', 5, 600, 'How long the visitor must stay on the second page.'],
          ];
          $imageFields = [
              ['image_google', 'Google result image', 'Shows the search result to click.'],
              ['image_button', 'GET CODE button image', 'Shows the button that reveals the code.'],
              ['image_position', 'Position image', 'Shows where the button is on the page.'],
          ];
        @endphp

        @foreach ($textFields as [$name, $label, $type, $help])
          <div class="form-group @error($name) has-error @enderror">
            <label for="{{ $name }}">{{ $label }}</label>
            <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" class="form-control" maxlength="255" @required($name !== 'display_domain') value="{{ old($name, $campaign->{$name}) }}">
            <span class="help-block">@error($name){{ $message }}@else{{ $help }}@enderror</span>
          </div>
        @endforeach

        @foreach ($numberFields as [$name, $label, $min, $max, $help])
          <div class="form-group @error($name) has-error @enderror">
            <label for="{{ $name }}">{{ $label }}</label>
            <input type="number" id="{{ $name }}" name="{{ $name }}" class="form-control" min="{{ $min }}" max="{{ $max }}" step="1" required value="{{ old($name, $campaign->{$name}) }}">
            <span class="help-block">@error($name){{ $message }}@else{{ $help }}@enderror</span>
          </div>
        @endforeach

        <div class="form-group @error('starts_at') has-error @enderror">
          <label for="starts_at">Start date (optional)</label>
          <input type="datetime-local" id="starts_at" name="starts_at" class="form-control" value="{{ old('starts_at', $campaign->starts_at?->format('Y-m-d\TH:i')) }}">
          @error('starts_at')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group @error('ends_at') has-error @enderror">
          <label for="ends_at">End date (optional)</label>
          <input type="datetime-local" id="ends_at" name="ends_at" class="form-control" value="{{ old('ends_at', $campaign->ends_at?->format('Y-m-d\TH:i')) }}">
          @error('ends_at')<span class="help-block">{{ $message }}</span>@enderror
        </div>

        @foreach ($imageFields as [$name, $label, $help])
          <div class="form-group @error($name) has-error @enderror">
            <label for="{{ $name }}">{{ $label }}</label>
            @if ($campaign->{$name})
              <div style="margin-bottom: 8px">
                <img src="{{ Storage::disk('public')->url($campaign->{$name}) }}" alt="{{ $label }}" style="max-width: 240px; max-height: 160px">
                <label class="checkbox-inline" style="margin-left: 12px">
                  <input type="checkbox" name="remove_{{ $name }}" value="1"> Remove
                </label>
              </div>
            @endif
            <input type="file" id="{{ $name }}" name="{{ $name }}" accept="image/jpeg,image/png,image/webp">
            <span class="help-block">@error($name){{ $message }}@else{{ $help }} JPG, PNG or WebP, up to 1 MB.@enderror</span>
          </div>
        @endforeach

        <div class="form-group @error('notes') has-error @enderror">
          <label for="notes">Notes (optional)</label>
          <textarea id="notes" name="notes" class="form-control" rows="3" maxlength="5000">{{ old('notes', $campaign->notes) }}</textarea>
          @error('notes')<span class="help-block">{{ $message }}</span>@enderror
        </div>

        <div class="checkbox">
          <label>
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $campaign->is_active ?? true))>
            Active
          </label>
        </div>
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-default">Cancel</a>
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      /* Suggest a display domain that hides the last 3 characters of the name part */
      var $display = $('#display_domain');
      var edited = $display.val() !== '';

      $display.on('input', function () {
        edited = $display.val() !== '';
      });

      $('#domain').on('input', function () {
        if (edited) {
          return;
        }

        var host = $(this).val().trim().toLowerCase().replace(/^[a-z][a-z0-9+.-]*:\/\//, '').split('/')[0];
        var dot = host.lastIndexOf('.');
        var name = dot === -1 ? host : host.slice(0, dot);

        $display.attr('placeholder', dot === -1 || name.length <= 3 ? host : name.slice(0, -3) + '***' + host.slice(dot));
      });

      $('#copy-embed').on('click', function () {
        var $button = $(this);
        var text = $('#embed-code').val();

        if (navigator.clipboard) {
          navigator.clipboard.writeText(text).then(function () {
            $button.text('Copied');
          });
        } else {
          $('#embed-code').trigger('select');
          document.execCommand('copy');
          $button.text('Copied');
        }
      });
    });
  </script>
@endpush
