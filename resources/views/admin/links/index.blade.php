@extends('layouts.admin')

@section('title', 'Links')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <form action="{{ route('admin.links.index') }}" method="get" class="form-inline">
        <input type="text" name="q" class="form-control input-sm" placeholder="Alias or URL" value="{{ $search }}">
        <select name="status" class="form-control input-sm">
          <option value="">All statuses</option>
          @foreach (App\Enums\LinkStatus::cases() as $case)
            <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.links.index') }}" class="btn btn-default btn-sm">Reset</a>
      </form>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Alias</th>
            <th>Destination</th>
            <th>Member</th>
            <th>Level</th>
            <th>Views</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($links as $link)
            <tr>
              <td><code>{{ $link->alias }}</code></td>
              <td style="max-width: 280px; overflow: hidden; text-overflow: ellipsis">{{ $link->url }}</td>
              <td>{{ $link->user?->username }}</td>
              <td>{{ $link->level }}</td>
              <td>{{ number_format($link->views_count) }}</td>
              <td>
                <span @class(['label', 'label-success' => $link->status === App\Enums\LinkStatus::Active, 'label-default' => $link->status === App\Enums\LinkStatus::Hidden, 'label-danger' => $link->status === App\Enums\LinkStatus::Banned])>{{ $link->status->label() }}</span>
              </td>
              <td class="text-right">
                @if ($link->status !== App\Enums\LinkStatus::Banned)
                  <form action="{{ route('admin.links.ban', $link) }}" method="post" class="form-inline" style="display: inline" onsubmit="return confirm('Ban this link? Pending views are cancelled and released earnings are taken back.')">
                    @csrf
                    <input type="text" name="reason" class="form-control input-sm" maxlength="200" required placeholder="Reason">
                    <button type="submit" class="btn btn-danger btn-xs">Ban</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No links found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($links->hasPages())
      <div class="box-footer">{{ $links->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
