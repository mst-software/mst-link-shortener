@extends('layouts.admin')

@section('title', 'Ad slots')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Ad slots</h3>
      <div class="box-tools">
        <a href="{{ route('admin.ad-slots.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> New ad slot</a>
      </div>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Name</th>
            <th>Placement</th>
            <th>Device</th>
            <th>Network</th>
            <th>Order</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($slots as $slot)
            <tr>
              <td>{{ $slot->name }}</td>
              <td>{{ $slot->placement->label() }}</td>
              <td>{{ $slot->device->label() }}</td>
              <td>{{ $slot->network ?? '-' }}</td>
              <td>{{ $slot->sort_order }}</td>
              <td>
                @if ($slot->is_active)
                  <span class="label label-success">Active</span>
                @else
                  <span class="label label-default">Off</span>
                @endif
              </td>
              <td class="text-right">
                <a href="{{ route('admin.ad-slots.edit', $slot) }}" class="btn btn-default btn-xs">Edit</a>
                <form action="{{ route('admin.ad-slots.destroy', $slot) }}" method="post" style="display: inline" onsubmit="return confirm('Delete this ad slot?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No ad slots yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
