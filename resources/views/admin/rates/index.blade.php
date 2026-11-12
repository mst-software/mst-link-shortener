@extends('layouts.admin')

@section('title', 'Rates')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Rates per 1,000 valid views (USD)</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Country</th>
            <th>Level 1</th>
            <th>Level 2</th>
            <th>Level 3</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $country => $levels)
            <tr>
              <td>
                {{ $country === 'XX' ? 'XX (all other countries)' : $country }}
                <form action="{{ route('admin.rates.update', $country) }}" method="post" id="rate-{{ $country }}">
                  @csrf
                  @method('PUT')
                </form>
              </td>
              @foreach ([1, 2, 3] as $level)
                <td>
                  <input type="number" form="rate-{{ $country }}" name="level_{{ $level }}" class="form-control input-sm" min="0" max="1000" step="0.001" required value="{{ number_format(($levels[$level] ?? 0) / 1000000, 3, '.', '') }}">
                </td>
              @endforeach
              <td class="text-right">
                <button type="submit" form="rate-{{ $country }}" class="btn btn-primary btn-xs">Save</button>
                @if ($country !== 'XX')
                  <form action="{{ route('admin.rates.destroy', $country) }}" method="post" style="display: inline" onsubmit="return confirm('Delete the rates of {{ $country }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">No rates yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Add or replace a country</h3></div>
    <form action="{{ route('admin.rates.store') }}" method="post">
      @csrf
      <div class="box-body">
        <div class="form-group @error('country') has-error @enderror">
          <label for="country">Country code</label>
          <input type="text" id="country" name="country" class="form-control" maxlength="2" required placeholder="US" value="{{ old('country') }}">
          <span class="help-block">@error('country'){{ $message }}@else Two letters, or XX for all other countries.@enderror</span>
        </div>
        @foreach ([1, 2, 3] as $level)
          <div class="form-group @error('level_'.$level) has-error @enderror">
            <label for="new_level_{{ $level }}">Level {{ $level }} rate (USD per 1,000 views)</label>
            <input type="number" id="new_level_{{ $level }}" name="level_{{ $level }}" class="form-control" min="0" max="1000" step="0.001" required value="{{ old('level_'.$level) }}">
            @error('level_'.$level)<span class="help-block">{{ $message }}</span>@enderror
          </div>
        @endforeach
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save country</button>
      </div>
    </form>
  </div>
@endsection
