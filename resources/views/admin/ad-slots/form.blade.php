@extends('layouts.admin')

@section('title', $slot->exists ? 'Edit ad slot' : 'New ad slot')

@section('content')
  <div class="box box-primary">
    <form action="{{ $slot->exists ? route('admin.ad-slots.update', $slot) : route('admin.ad-slots.store') }}" method="post">
      @csrf
      @if ($slot->exists)
        @method('PUT')
      @endif
      <div class="box-body">
        <div class="form-group @error('name') has-error @enderror">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" class="form-control" maxlength="255" required value="{{ old('name', $slot->name) }}">
          @error('name')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group @error('placement') has-error @enderror">
          <label for="placement">Placement</label>
          <select id="placement" name="placement" class="form-control" required>
            @foreach (App\Enums\AdPlacement::cases() as $placement)
              <option value="{{ $placement->value }}" @selected(old('placement', $slot->placement?->value) === $placement->value)>{{ $placement->label() }}</option>
            @endforeach
          </select>
          @error('placement')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group @error('device') has-error @enderror">
          <label for="device">Device</label>
          <select id="device" name="device" class="form-control" required>
            @foreach (App\Enums\AdDevice::cases() as $device)
              <option value="{{ $device->value }}" @selected(old('device', $slot->device?->value ?? 'all') === $device->value)>{{ $device->label() }}</option>
            @endforeach
          </select>
          @error('device')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group @error('network') has-error @enderror">
          <label for="network">Ad network (optional)</label>
          <input type="text" id="network" name="network" class="form-control" maxlength="255" value="{{ old('network', $slot->network) }}">
          @error('network')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group @error('code') has-error @enderror">
          <label for="code">Ad code</label>
          <textarea id="code" name="code" class="form-control" rows="8" required style="font-family: monospace">{{ old('code', $slot->code) }}</textarea>
          <span class="help-block">@error('code'){{ $message }}@else This code is printed on the visitor page exactly as entered, without escaping, so it can run scripts. Only paste code from networks you trust.@enderror</span>
        </div>
        <div class="form-group @error('sort_order') has-error @enderror">
          <label for="sort_order">Sort order</label>
          <input type="number" id="sort_order" name="sort_order" class="form-control" min="0" max="65535" value="{{ old('sort_order', $slot->sort_order ?? 0) }}">
          @error('sort_order')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="checkbox">
          <label>
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $slot->is_active ?? true))>
            Active
          </label>
        </div>
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.ad-slots.index') }}" class="btn btn-default">Cancel</a>
      </div>
    </form>
  </div>
@endsection
