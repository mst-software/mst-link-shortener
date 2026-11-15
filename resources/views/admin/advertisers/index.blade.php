@extends('layouts.admin')

@section('title', 'Advertisers')

@section('content')
  <div class="box box-primary">
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Campaigns</th>
            <th>Balance</th>
            <th>Total topped up</th>
            <th>Total spent</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($advertisers as $advertiser)
            <tr>
              <td><a href="{{ route('admin.advertisers.show', $advertiser) }}">{{ $advertiser->username }}</a></td>
              <td>{{ $advertiser->email }}</td>
              <td>{{ $advertiser->campaigns_count }}</td>
              <td>${{ number_format($advertiser->balance_micro / 1_000_000, 2) }}</td>
              <td>${{ number_format(($toppedUp[$advertiser->id] ?? 0) / 1_000_000, 2) }}</td>
              <td>${{ number_format(($spent[$advertiser->id] ?? 0) / 1_000_000, 2) }}</td>
              <td>
                <span class="label {{ $advertiser->isActive() ? 'label-success' : 'label-danger' }}">{{ $advertiser->isActive() ? 'Active' : 'Locked' }}</span>
              </td>
              <td class="text-right">
                <a href="{{ route('admin.advertisers.show', $advertiser) }}" class="btn btn-default btn-xs">View</a>
                <form action="{{ route('admin.advertisers.toggle', $advertiser) }}" method="post" style="display: inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="btn btn-xs {{ $advertiser->isActive() ? 'btn-danger' : 'btn-success' }}">{{ $advertiser->isActive() ? 'Lock' : 'Unlock' }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted">No advertisers yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($advertisers->hasPages())
      <div class="box-footer">{{ $advertisers->links() }}</div>
    @endif
  </div>
@endsection
