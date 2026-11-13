@extends('layouts.admin')

@section('title', 'Campaigns')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Campaigns</h3>
      <div class="box-tools">
        @if ($pendingOnly)
          <a href="{{ route('admin.campaigns.index') }}" class="btn btn-default btn-sm">Show all</a>
        @else
          <a href="{{ route('admin.campaigns.index', ['review' => 'pending']) }}" class="btn btn-default btn-sm">Pending review</a>
        @endif
        <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> New campaign</a>
      </div>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Name</th>
            <th>Owner</th>
            <th>Keyword</th>
            <th>Domain</th>
            <th>Weight</th>
            <th>Daily limit</th>
            <th>Codes issued today</th>
            <th>Valid views</th>
            <th>Reports</th>
            <th>Review</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($campaigns as $campaign)
            <tr>
              <td>{{ $campaign->name }}</td>
              <td>{{ $campaign->owner?->username ?? 'Admin' }}</td>
              <td>{{ $campaign->keyword }}</td>
              <td>{{ $campaign->domain }}</td>
              <td>{{ $campaign->weight }}</td>
              <td>{{ $campaign->daily_limit === 0 ? 'No limit' : number_format($campaign->daily_limit) }}</td>
              <td>{{ number_format($stats[$campaign->id]['issued_today']) }}</td>
              <td>{{ number_format($stats[$campaign->id]['valid_views']) }}</td>
              <td>{{ number_format($stats[$campaign->id]['reports']) }}</td>
              <td>
                <span @class([
                  'label',
                  'label-warning' => $campaign->review_status === \App\Enums\ReviewStatus::Pending,
                  'label-success' => $campaign->review_status === \App\Enums\ReviewStatus::Approved,
                  'label-danger' => $campaign->review_status === \App\Enums\ReviewStatus::Rejected,
                ])>{{ $campaign->review_status->label() }}</span>
                @if ($campaign->review_status === \App\Enums\ReviewStatus::Pending)
                  <form action="{{ route('admin.campaigns.review', $campaign) }}" method="post" style="margin-top: 4px">
                    @csrf
                    @method('PATCH')
                    <input type="text" name="review_note" class="form-control input-sm" maxlength="255" placeholder="Note (needed to reject)" style="margin-bottom: 4px">
                    <button type="submit" name="review_status" value="approved" class="btn btn-success btn-xs">Approve</button>
                    <button type="submit" name="review_status" value="rejected" class="btn btn-danger btn-xs">Reject</button>
                  </form>
                @endif
              </td>
              <td>
                <form action="{{ route('admin.campaigns.toggle', $campaign) }}" method="post" style="display: inline">
                  @csrf
                  @method('PATCH')
                  @if ($campaign->is_active)
                    <button type="submit" class="btn btn-success btn-xs" title="Switch off">Active</button>
                  @else
                    <button type="submit" class="btn btn-default btn-xs" title="Switch on">Off</button>
                  @endif
                </form>
              </td>
              <td class="text-right">
                <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-default btn-xs">Edit</a>
                <form action="{{ route('admin.campaigns.destroy', $campaign) }}" method="post" style="display: inline" onsubmit="return confirm('Delete this campaign?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="12" class="text-center text-muted">No campaigns yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($campaigns->hasPages())
      <div class="box-footer">{{ $campaigns->links() }}</div>
    @endif
  </div>
@endsection
