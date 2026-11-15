@extends('layouts.advertiser')

@section('title', 'Wallet')

@section('content')
  <div class="row">
    <div class="col-md-4">
      <div class="small-box bg-green">
        <div class="inner">
          <h3>${{ number_format(auth()->user()->balance_micro / 1_000_000, 2) }}</h3>
          <p>Available balance</p>
        </div>
        <div class="icon"><i class="fa fa-wallet"></i></div>
      </div>
      <p class="text-muted">
        Minimum top up: ${{ number_format($minimumMicro / 1_000_000, 2) }}
        ({{ number_format($minimumVnd) }} VND). Rate: 1 USD = {{ number_format($vndPerUsd) }} VND.
      </p>
    </div>

    <div class="col-md-4">
      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Bank transfer (VND)</h3></div>
        <form action="{{ route('advertiser.topups.sepay') }}" method="post">
          @csrf
          <div class="box-body">
            <div class="form-group @error('vnd') has-error @enderror">
              <label for="vnd">Amount in VND</label>
              <input type="number" id="vnd" name="vnd" class="form-control" min="{{ $minimumVnd }}" step="1" required value="{{ old('vnd', $minimumVnd) }}" data-rate="{{ $vndPerUsd }}">
              <span class="help-block">@error('vnd'){{ $message }}@else You will receive about $<span id="vnd-preview">0.00</span>.@enderror</span>
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-primary">Get transfer details</button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-md-4">
      <div class="box box-info">
        <div class="box-header with-border"><h3 class="box-title">PayPal (USD)</h3></div>
        <form action="{{ route('advertiser.topups.paypal') }}" method="post">
          @csrf
          <div class="box-body">
            <div class="form-group @error('usd') has-error @enderror">
              <label for="usd">Amount in USD</label>
              <input type="number" id="usd" name="usd" class="form-control" min="{{ $minimumMicro / 1_000_000 }}" step="0.01" required value="{{ old('usd', $minimumMicro / 1_000_000) }}">
              @error('usd')<span class="help-block">{{ $message }}</span>@enderror
            </div>
          </div>
          <div class="box-footer">
            <button type="submit" class="btn btn-info">Pay with PayPal</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  @if ($pendingTransfer)
    <div class="box box-warning">
      <div class="box-header with-border"><h3 class="box-title">Waiting for your transfer</h3></div>
      <div class="box-body">
        <div class="row">
          <div class="col-sm-4 text-center">
            <img src="{{ $pendingTransfer->sepayQrUrl() }}" alt="Transfer QR code" style="max-width: 100%; max-height: 260px">
          </div>
          <div class="col-sm-8">
            <table class="table table-condensed">
              <tr><th style="width: 40%">Bank</th><td>{{ $bank['name'] }}</td></tr>
              <tr><th>Account number</th><td>{{ $bank['account_number'] }}</td></tr>
              <tr><th>Account name</th><td>{{ $bank['account_name'] }}</td></tr>
              <tr><th>Amount</th><td>{{ number_format($pendingTransfer->original_amount) }} VND (about ${{ number_format($pendingTransfer->amount_micro / 1_000_000, 2) }})</td></tr>
              <tr><th>Transfer note</th><td><strong>{{ $pendingTransfer->reference }}</strong></td></tr>
            </table>
            <p class="text-muted">
              Use the exact amount and transfer note. Your balance is updated within a few minutes after the transfer.
              This request expires {{ $pendingTransfer->expires_at->diffForHumans() }}.
            </p>
          </div>
        </div>
      </div>
    </div>
  @endif

  <div class="row">
    <div class="col-md-6">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Top ups</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($topUps as $topUp)
                <tr>
                  <td>{{ $topUp->created_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $topUp->provider->label() }}</td>
                  <td>${{ number_format($topUp->amount_micro / 1_000_000, 2) }}</td>
                  <td>{{ $topUp->status->label() }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted">No top ups yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($topUps->hasPages())
          <div class="box-footer">{{ $topUps->links() }}</div>
        @endif
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
                <tr><td colspan="4" class="text-center text-muted">No transactions yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($transactions->hasPages())
          <div class="box-footer">{{ $transactions->links() }}</div>
        @endif
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var $vnd = $('#vnd');

      function preview() {
        var usd = (parseInt($vnd.val(), 10) || 0) / parseInt($vnd.data('rate'), 10);
        $('#vnd-preview').text(usd.toFixed(2));
      }

      $vnd.on('input', preview);
      preview();
    });
  </script>
@endpush
