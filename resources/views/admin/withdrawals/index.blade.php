@extends('layouts.admin')

@section('title', 'Withdrawals')

@section('content')
  @if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Withdrawal requests</h3>
      <div class="box-tools">
        <a href="{{ route('admin.withdrawals.index') }}" @class(['btn', 'btn-xs', 'btn-primary' => $status === null, 'btn-default' => $status !== null])>All</a>
        @foreach ($statuses as $option)
          <a href="{{ route('admin.withdrawals.index', ['status' => $option->value]) }}" @class(['btn', 'btn-xs', 'btn-primary' => $status === $option, 'btn-default' => $status !== $option])>{{ $option->label() }}</a>
        @endforeach
      </div>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Requested</th>
            <th>Member</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Traffic source</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($withdrawals as $withdrawal)
            <tr>
              <td>{{ $withdrawal->created_at->format('d/m/Y H:i') }}</td>
              <td>{{ $withdrawal->user?->username }}</td>
              <td>{{ \App\Support\Money::format($withdrawal->amount_micro, 2) }}</td>
              <td>
                {{ $withdrawal->method->label() }}
                @foreach ($withdrawal->details ?? [] as $key => $value)
                  <br><small class="text-muted">{{ str_replace('_', ' ', $key) }}: {{ $value }}</small>
                @endforeach
              </td>
              <td style="white-space: pre-line">{{ $withdrawal->traffic_source }}</td>
              <td>
                <span class="label {{ $withdrawal->status->badgeClass() }}">{{ $withdrawal->status->label() }}</span>
                @if ($withdrawal->admin_note)
                  <br><small class="text-muted">{{ $withdrawal->admin_note }}</small>
                @endif
              </td>
              <td style="min-width: 220px">
                @if ($withdrawal->isPending())
                  <form action="{{ route('admin.withdrawals.approve', $withdrawal) }}" method="post" style="margin-bottom: 6px">
                    @csrf
                    <button type="submit" class="btn btn-success btn-xs">Approve</button>
                  </form>
                  <form action="{{ route('admin.withdrawals.reject', $withdrawal) }}" method="post">
                    @csrf
                    <div class="input-group input-group-sm">
                      <input type="text" name="admin_note" class="form-control" placeholder="Reason (required)" maxlength="1000" required>
                      <span class="input-group-btn">
                        <button type="submit" class="btn btn-danger btn-flat">Reject</button>
                      </span>
                    </div>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No withdrawals found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($withdrawals->hasPages())
      <div class="box-footer">{{ $withdrawals->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
