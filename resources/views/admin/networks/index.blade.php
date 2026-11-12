@extends('layouts.admin')

@section('title', 'Blocked networks')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Block a network</h3></div>
    <form action="{{ route('admin.blocked-networks.store') }}" method="post">
      @csrf
      <div class="box-body">
        <div class="form-group @error('cidr') has-error @enderror">
          <label for="cidr">IP address or CIDR range</label>
          <input type="text" id="cidr" name="cidr" class="form-control" maxlength="64" required placeholder="203.0.113.0/24" value="{{ old('cidr') }}">
          @error('cidr')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group @error('label') has-error @enderror">
          <label for="label">Label (optional)</label>
          <input type="text" id="label" name="label" class="form-control" maxlength="255" value="{{ old('label') }}">
          @error('label')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <p class="text-muted">To import a list, run <code>php artisan networks:import path/to/file.txt</code> (one CIDR per line, optionally followed by <code># label</code>).</p>
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Block network</button>
      </div>
    </form>
  </div>

  <div class="box box-primary">
    <div class="box-header with-border"><h3 class="box-title">Blocked networks</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Network</th>
            <th>Label</th>
            <th>Added</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($networks as $network)
            <tr>
              <td><code>{{ $network->cidr }}</code></td>
              <td>
                <form action="{{ route('admin.blocked-networks.update', $network) }}" method="post" class="form-inline">
                  @csrf
                  @method('PUT')
                  <input type="text" name="label" class="form-control input-sm" maxlength="255" value="{{ $network->label }}">
                  <button type="submit" class="btn btn-default btn-xs">Save</button>
                </form>
              </td>
              <td>{{ $network->created_at?->format('d/m/Y H:i') }}</td>
              <td class="text-right">
                <form action="{{ route('admin.blocked-networks.destroy', $network) }}" method="post" style="display: inline" onsubmit="return confirm('Unblock this network?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-danger btn-xs">Unblock</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted">No blocked networks yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($networks->hasPages())
      <div class="box-footer">{{ $networks->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
