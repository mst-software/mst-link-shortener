@extends('layouts.advertiser')

@section('title', $campaign->exists ? 'Edit campaign' : 'New campaign')

@section('content')
  @if ($campaign->exists)
    @if ($campaign->review_status === \App\Enums\ReviewStatus::Rejected)
      <div class="alert alert-danger">
        <strong>Rejected.</strong> {{ $campaign->review_note }}
        <br>Fix the issue and save the campaign to send it for review again.
      </div>
    @elseif ($campaign->review_status === \App\Enums\ReviewStatus::Pending)
      <div class="alert alert-warning">This campaign is waiting for review. It will start running once it is approved.</div>
    @else
      <div class="box box-info">
        <div class="box-header with-border"><h3 class="box-title">Embed code</h3></div>
        <div class="box-body">
          <p class="text-muted">Add this snippet to every page of your site that should show the keyword widget.</p>
          <textarea id="embed-code" class="form-control" rows="2" readonly style="font-family: monospace">&lt;script async src="{{ url('/js/widget.js') }}" data-campaign="{{ $campaign->widget_key }}"&gt;&lt;/script&gt;</textarea>
          <button type="button" id="copy-embed" class="btn btn-default btn-sm" style="margin-top: 8px"><i class="fa fa-copy"></i> Copy</button>
        </div>
      </div>
    @endif
  @endif

  <div class="box box-primary">
    <form action="{{ $campaign->exists ? route('advertiser.campaigns.update', $campaign) : route('advertiser.campaigns.store') }}" method="post" enctype="multipart/form-data">
      @csrf
      @if ($campaign->exists)
        @method('PUT')
      @endif
      <div class="box-body">
        @php
          $textFields = [
              ['name', 'Name', 'For your own reference.'],
              ['keyword', 'Keyword', 'The phrase visitors search for on Google to find your site. Changing it sends the campaign for review again.'],
              ['domain', 'Website domain', 'For example example.com. The widget only works on this domain and its subdomains. Changing it sends the campaign for review again.'],
          ];
          $imageFields = [
              ['image_google', 'Google result image', 'Shows visitors the search result to click.'],
              ['image_button', 'GET CODE button image', 'Shows visitors the button that reveals the code.'],
              ['image_position', 'Position image', 'Shows visitors where the button is on the page.'],
          ];
        @endphp

        @foreach ($textFields as [$name, $label, $help])
          <div class="form-group @error($name) has-error @enderror">
            <label for="{{ $name }}">{{ $label }}</label>
            <input type="text" id="{{ $name }}" name="{{ $name }}" class="form-control" maxlength="255" required value="{{ old($name, $campaign->{$name}) }}">
            <span class="help-block">@error($name){{ $message }}@else{{ $help }}@enderror</span>
          </div>
        @endforeach

        <div class="form-group @error('daily_limit') has-error @enderror">
          <label for="daily_limit">Daily limit</label>
          <input type="number" id="daily_limit" name="daily_limit" class="form-control" min="0" max="1000000" step="1" required value="{{ old('daily_limit', $campaign->daily_limit ?? 0) }}">
          <span class="help-block">@error('daily_limit'){{ $message }}@else Codes issued per day. 0 means no limit.@enderror</span>
        </div>

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
            <label for="{{ $name }}">{{ $label }} (optional)</label>
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
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">{{ $campaign->exists ? 'Save' : 'Submit for review' }}</button>
        <a href="{{ route('advertiser.campaigns.index') }}" class="btn btn-default">Cancel</a>
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
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
