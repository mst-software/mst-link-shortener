@extends('layouts.admin')

@section('title', 'Fraud flags')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">{{ $showResolved ? 'All flags' : 'Open flags' }}</h3>
      <div class="box-tools">
        @if ($showResolved)
          <a href="{{ route('admin.fraud-flags.index') }}" class="btn btn-default btn-sm">Show open only</a>
        @else
          <a href="{{ route('admin.fraud-flags.index', ['resolved' => 1]) }}" class="btn btn-default btn-sm">Show handled too</a>
        @endif
      </div>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Date</th>
            <th>Reason</th>
            <th>Member</th>
            <th>Link</th>
            <th>Details</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($flags as $flag)
            <tr>
              <td>{{ $flag->created_at?->format('d/m/Y H:i') }}</td>
              <td>{{ str($flag->reason)->replace('_', ' ')->ucfirst() }}</td>
              <td>{{ $flag->user?->username ?? '-' }}</td>
              <td>
                @if ($flag->link)
                  <code>{{ $flag->link->alias }}</code>
                  @if ($flag->link->status === App\Enums\LinkStatus::Banned)
                    <span class="label label-danger">Banned</span>
                  @endif
                @else
                  -
                @endif
              </td>
              <td>
                @foreach ((array) $flag->details as $key => $value)
                  @if ($value !== null && $value !== '')
                    <div><strong>{{ str($key)->replace('_', ' ')->ucfirst() }}:</strong> {{ is_scalar($value) ? $value : json_encode($value) }}</div>
                  @endif
                @endforeach
              </td>
              <td>
                @if ($flag->resolved_at)
                  <span class="label label-default">Handled</span>
                @else
                  <span class="label label-warning">Open</span>
                @endif
              </td>
              <td class="text-right">
                @unless ($flag->resolved_at)
                  <form action="{{ route('admin.fraud-flags.resolve', $flag) }}" method="post" style="display: inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-default btn-xs">Mark handled</button>
                  </form>
                @endunless
                @if ($flag->link && $flag->link->status !== App\Enums\LinkStatus::Banned)
                  <form action="{{ route('admin.fraud-flags.ban-link', $flag) }}" method="post" style="display: inline" onsubmit="return confirm('Ban this link? Pending views are cancelled and released earnings are taken back.')">
                    @csrf
                    <input type="hidden" name="reason" value="{{ str($flag->reason)->replace('_', ' ')->ucfirst() }}">
                    <button type="submit" class="btn btn-danger btn-xs">Ban link</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No fraud flags.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($flags->hasPages())
      <div class="box-footer">{{ $flags->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
