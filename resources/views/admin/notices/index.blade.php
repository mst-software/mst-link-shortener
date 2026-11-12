@extends('layouts.admin')

@section('title', 'Notices')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Notices</h3>
      <div class="box-tools">
        <a href="{{ route('admin.notices.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> New notice</a>
      </div>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Title</th>
            <th>Status</th>
            <th>Published</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($notices as $notice)
            <tr>
              <td>{{ $notice->title }}</td>
              <td>
                @if ($notice->is_published)
                  <span class="label label-success">Published</span>
                @else
                  <span class="label label-default">Draft</span>
                @endif
              </td>
              <td>{{ $notice->published_at?->format('d/m/Y H:i') ?? '-' }}</td>
              <td class="text-right">
                <a href="{{ route('admin.notices.edit', $notice) }}" class="btn btn-default btn-xs">Edit</a>
                <form action="{{ route('admin.notices.destroy', $notice) }}" method="post" class="inline" style="display: inline" onsubmit="return confirm('Delete this notice?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted">No notices yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($notices->hasPages())
      <div class="box-footer">{{ $notices->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
