@extends('layouts.advertiser')

@section('title', 'Campaigns')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Your campaigns</h3>
      <div class="box-tools">
        <a href="{{ route('advertiser.campaigns.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> New campaign</a>
      </div>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Name</th>
            <th>Keyword</th>
            <th>Domain</th>
            <th>Review</th>
            <th>Running</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($campaigns as $campaign)
            <tr>
              <td>{{ $campaign->name }}</td>
              <td>{{ $campaign->keyword }}</td>
              <td>{{ $campaign->domain }}</td>
              <td>
                <span @class([
                  'label',
                  'label-warning' => $campaign->review_status === \App\Enums\ReviewStatus::Pending,
                  'label-success' => $campaign->review_status === \App\Enums\ReviewStatus::Approved,
                  'label-danger' => $campaign->review_status === \App\Enums\ReviewStatus::Rejected,
                ])>{{ $campaign->review_status->label() }}</span>
              </td>
              <td>
                @if ($campaign->review_status === \App\Enums\ReviewStatus::Approved)
                  <form action="{{ route('advertiser.campaigns.toggle', $campaign) }}" method="post" style="display: inline">
                    @csrf
                    @method('PATCH')
                    @if ($campaign->is_active)
                      <button type="submit" class="btn btn-success btn-xs" title="Pause">Active</button>
                    @else
                      <button type="submit" class="btn btn-default btn-xs" title="Resume">Paused</button>
                    @endif
                  </form>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td class="text-right">
                <a href="{{ route('advertiser.campaigns.edit', $campaign) }}" class="btn btn-default btn-xs">Edit</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted">No campaigns yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($campaigns->hasPages())
      <div class="box-footer">{{ $campaigns->links() }}</div>
    @endif
  </div>
@endsection
