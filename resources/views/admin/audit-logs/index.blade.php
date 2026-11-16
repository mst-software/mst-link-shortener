@extends('layouts.admin')

@section('title', 'Audit log')

@section('content')
  @if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  <div class="row">
    <div class="col-sm-6">
      <div class="small-box bg-aqua">
        <div class="inner">
          <h3>{{ number_format($stats['total']) }}</h3>
          <p>Matching entries</p>
        </div>
      </div>
    </div>
    <div class="col-sm-6">
      <div class="small-box bg-green">
        <div class="inner">
          <h3>{{ number_format($stats['today']) }}</h3>
          <p>Of which today</p>
        </div>
      </div>
    </div>
  </div>

  <div class="box box-primary">
    <div class="box-header with-border">
      <form action="{{ route('admin.audit-logs.index') }}" method="get" class="form-inline">
        <select name="action" class="form-control input-sm">
          <option value="">All actions</option>
          @foreach (App\Enums\AdminAction::cases() as $case)
            <option value="{{ $case->value }}" @selected($filters['action'] === $case)>{{ $case->label() }}</option>
          @endforeach
        </select>
        <input type="text" name="admin" class="form-control input-sm" placeholder="Admin username" value="{{ $filters['admin'] }}" maxlength="100">
        <input type="text" name="q" class="form-control input-sm" placeholder="User, email, reason or IP" value="{{ $filters['q'] }}" maxlength="100">
        <select name="time_range" class="form-control input-sm">
          <option value="">Any time</option>
          @foreach (App\Enums\TimeRange::cases() as $case)
            <option value="{{ $case->value }}" @selected($filters['time_range'] === $case)>{{ $case->label() }}</option>
          @endforeach
        </select>
        <label class="sr-only" for="from">From</label>
        <input type="date" id="from" name="from" class="form-control input-sm" value="{{ $filters['from'] }}" title="From">
        <label class="sr-only" for="to">To</label>
        <input type="date" id="to" name="to" class="form-control input-sm" value="{{ $filters['to'] }}" title="To">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-default btn-sm">Reset</a>
      </form>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Date</th>
            <th>Admin</th>
            <th>Action</th>
            <th>User</th>
            <th>Reason</th>
            <th>Before</th>
            <th>After</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($logs as $log)
            <tr>
              <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
              <td>{{ $log->admin?->username ?? '-' }}</td>
              <td>{{ $log->action->label() }}</td>
              <td>
                @if ($log->targetUser)
                  <a href="{{ route('admin.users.show', $log->targetUser) }}">{{ $log->targetUser->username }}</a>
                @else
                  -
                @endif
              </td>
              <td>{{ $log->reason }}</td>
              <td><x-admin.audit-values :values="$log->old_values" /></td>
              <td><x-admin.audit-values :values="$log->new_values" /></td>
              <td>{{ $log->ip_address }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted">No admin actions recorded.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($logs->hasPages())
      <div class="box-footer">{{ $logs->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
