@extends('layouts.admin')

@section('title', $advertiser->username)

@section('content')
  <div class="box box-primary">
    <div class="box-body">
      <strong>{{ $advertiser->username }}</strong> &middot; {{ $advertiser->email }}
      &middot; Balance <strong>${{ number_format($advertiser->balance_micro / 1_000_000, 2) }}</strong>
      &middot; <span class="label {{ $advertiser->isActive() ? 'label-success' : 'label-danger' }}">{{ $advertiser->isActive() ? 'Active' : 'Locked' }}</span>
      <form action="{{ route('admin.advertisers.toggle', $advertiser) }}" method="post" class="pull-right">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-sm {{ $advertiser->isActive() ? 'btn-danger' : 'btn-success' }}">{{ $advertiser->isActive() ? 'Lock advertiser' : 'Unlock advertiser' }}</button>
      </form>
    </div>
  </div>

  <div class="box">
    <div class="box-header with-border"><h3 class="box-title">Campaigns</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead><tr><th>Name</th><th>Keyword</th><th>Domain</th><th>Review</th><th>Running</th><th>Price per view</th></tr></thead>
        <tbody>
          @forelse ($campaigns as $campaign)
            <tr>
              <td><a href="{{ route('admin.campaigns.edit', $campaign) }}">{{ $campaign->name }}</a></td>
              <td>{{ $campaign->keyword }}</td>
              <td>{{ $campaign->domain }}</td>
              <td>{{ $campaign->review_status->label() }}</td>
              <td>{{ $campaign->is_active ? 'Yes' : 'No' }}</td>
              <td>{{ $campaign->price_micro === null ? 'Default' : '$'.number_format($campaign->price_micro / 1_000_000, 4) }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted">No campaigns.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Top ups</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
              @forelse ($topUps as $topUp)
                <tr>
                  <td>{{ $topUp->created_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $topUp->provider->label() }}</td>
                  <td>{{ $topUp->reference }}</td>
                  <td>${{ number_format($topUp->amount_micro / 1_000_000, 2) }}</td>
                  <td>{{ $topUp->status->label() }}</td>
                  <td class="text-right">
                    @if ($topUp->status === \App\Enums\TopUpStatus::Mismatch)
                      <form action="{{ route('admin.top-ups.credit', $topUp) }}" method="post" onsubmit="return confirm('Credit this top up to the wallet? Check the bank statement first.')">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-xs">Credit manually</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted">No top ups.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Transactions</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance</th></tr></thead>
            <tbody>
              @forelse ($transactions as $transaction)
                <tr>
                  <td>{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $transaction->action->label() }}</td>
                  <td>{{ $transaction->type === 'in' ? '+' : '-' }}${{ number_format($transaction->amount_micro / 1_000_000, 4) }}</td>
                  <td>${{ number_format($transaction->balance_after_micro / 1_000_000, 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted">No transactions.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="box">
    <div class="box-header with-border"><h3 class="box-title">Latest visits (with reasons)</h3></div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead><tr><th>Time</th><th>Campaign</th><th>Country</th><th>IP</th><th>Status</th><th>Reason</th><th>Cost</th></tr></thead>
        <tbody>
          @forelse ($visits as $visit)
            <tr>
              <td>{{ $visit->created_at->format('d/m/Y H:i') }}</td>
              <td>{{ $visit->campaign?->name }}</td>
              <td>{{ $visit->country }}</td>
              <td>{{ $visit->ip }}</td>
              <td>{{ $visit->status->label() }}</td>
              <td>{{ $visit->invalid_reason?->label() }}</td>
              <td>${{ number_format($visit->advertiser_cost_micro / 1_000_000, 3) }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No visits.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
