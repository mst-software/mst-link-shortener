@extends('layouts.admin')

@section('title', 'Users')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <form action="{{ route('admin.users.index') }}" method="get" class="form-inline">
        <input type="text" name="q" class="form-control input-sm" placeholder="Username, email or ID" value="{{ $search }}" maxlength="100">
        <select name="role" class="form-control input-sm">
          <option value="">All roles</option>
          @foreach (App\Enums\UserRole::cases() as $case)
            <option value="{{ $case->value }}" @selected($role === $case)>{{ $case->label() }}</option>
          @endforeach
        </select>
        <select name="status" class="form-control input-sm">
          <option value="">All statuses</option>
          @foreach (App\Enums\UserStatus::cases() as $case)
            <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
          @endforeach
        </select>
        <select name="verified" class="form-control input-sm">
          <option value="">Verified or not</option>
          <option value="yes" @selected($verified === 'yes')>Email verified</option>
          <option value="no" @selected($verified === 'no')>Email not verified</option>
        </select>
        <select name="joined" class="form-control input-sm">
          <option value="">Joined any time</option>
          @foreach (App\Enums\TimeRange::cases() as $case)
            <option value="{{ $case->value }}" @selected($joined === $case)>Joined {{ strtolower($case->label()) }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-default btn-sm">Reset</a>
      </form>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Verified</th>
            <th>Balance</th>
            <th>Processing</th>
            <th>Links</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($users as $user)
            <tr>
              <td>{{ $user->id }}</td>
              <td><a href="{{ route('admin.users.show', $user) }}">{{ $user->username }}</a></td>
              <td>{{ $user->email }}</td>
              <td>{{ $user->role->label() }}</td>
              <td>
                <span @class(['label', 'label-success' => $user->isActive(), 'label-danger' => ! $user->isActive()])>{{ $user->status->label() }}</span>
              </td>
              <td>{{ $user->hasVerifiedEmail() ? 'Yes' : 'No' }}</td>
              <td>{{ App\Support\Money::format($user->balance_micro, 2) }}</td>
              <td>{{ App\Support\Money::format($user->processing_micro, 2) }}</td>
              <td>{{ number_format($user->links_count) }}</td>
              <td>{{ $user->created_at?->format('d/m/Y') }}</td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-muted">No users found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($users->hasPages())
      <div class="box-footer">{{ $users->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
