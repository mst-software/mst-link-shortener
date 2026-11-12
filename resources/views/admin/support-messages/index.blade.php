@extends('layouts.admin')

@section('title', 'Support inbox')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Support inbox</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Received</th>
            <th>From</th>
            <th>Title</th>
            <th>Message</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($messages as $message)
            <tr>
              <td>{{ $message->created_at->format('d/m/Y H:i') }}</td>
              <td>{{ $message->name }}<br><small class="text-muted">{{ $message->email }} ({{ $message->user?->username }})</small></td>
              <td>{{ $message->title }}</td>
              <td style="white-space: pre-line">{{ $message->message }}</td>
              <td>
                @if ($message->isResolved())
                  <span class="label label-success">Resolved</span>
                @else
                  <span class="label label-warning">Open</span>
                @endif
              </td>
              <td class="text-right">
                @unless ($message->isResolved())
                  <form action="{{ route('admin.support-messages.resolve', $message) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-success btn-xs">Mark as resolved</button>
                  </form>
                @endunless
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted">No messages yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($messages->hasPages())
      <div class="box-footer">{{ $messages->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
