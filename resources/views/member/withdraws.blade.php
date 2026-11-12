@extends('layouts.member')

@section('title', 'Withdraws')

@section('content')
  @php($minimumLabel = \App\Support\Money::format($minimum, 2))

  @if ($errors->any())
    <div class="alert alert-danger">
      @foreach ($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <div class="row">
    <div class="col-sm-4">
      <x-member.stat-box color="aqua" label="Available balance" :value="\App\Support\Money::format($balance)" icon="money-bill" />
    </div>
    <div class="col-sm-4">
      <x-member.stat-box color="red" label="Processing" :value="\App\Support\Money::format($processing)" icon="share" />
    </div>
    <div class="col-sm-4">
      <x-member.stat-box color="green" label="Total paid" :value="\App\Support\Money::format($totalPaid)" icon="dollar-sign" />
    </div>
  </div>

  <div class="box box-primary">
    <div class="box-body table-responsive">
      <div class="text-center">
        <form id="withdraw-form" action="{{ route('member.withdraws.store') }}" method="post" class="text-left">
          @csrf
          <div class="modal fade" id="withdrawModal" tabindex="-1" role="dialog" aria-labelledby="withdrawModalLabel" style="display: none">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <button type="button" class="close" data-dismiss-withdraw aria-label="Close"><span aria-hidden="true">&times;</span></button>
                  <h4 class="modal-title" id="withdrawModalLabel">Withdraw</h4>
                </div>
                <div class="modal-body">
                  <div class="form-group @error('amount') has-error @enderror">
                    <label for="amount">Amount (USD)</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" class="form-control" placeholder="{{ $minimumLabel }}" value="{{ old('amount') }}" required>
                    <span class="help-block">Available balance: {{ \App\Support\Money::format($balance) }}. Minimum: {{ $minimumLabel }}.</span>
                  </div>
                  <div class="has-error">
                    <div class="help-block">
                      <strong>Check and update your traffic source if it changed. A wrong source will not be paid.</strong>
                    </div>
                  </div>
                  <div class="form-group @error('traffic_source') has-error @enderror">
                    <label for="traffic_source">Traffic source</label>
                    <p>Where did you share your links? Please be specific so the payment team can review and pay you as fast as possible (for example a YouTube channel link, a website address, a Facebook or Telegram group link).</p>
                    <textarea id="traffic_source" name="traffic_source" class="form-control" rows="5" maxlength="2000" required placeholder="Example: https://www.youtube.com/channel/..., https://www.facebook.com/groups/...">{{ old('traffic_source') }}</textarea>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="submit" class="btn btn-primary">Withdraw</button>
                </div>
              </div>
            </div>
          </div>
        </form>

        @if ($canWithdraw)
          <a href="#" id="withdraw-open" class="btn btn-success btn-lg">Withdraw</a>
        @else
          <a href="#" class="btn btn-success btn-lg" disabled="disabled">Withdraw</a>
        @endif
      </div>
      <hr>

      <p>Minimum withdrawal: <b>{{ $minimumLabel }}</b></p>
      <p><b>Important notes:</b></p>
      <p>+ When your balance reaches the minimum amount, you can request a withdrawal with the button above. The payment is then sent to your account within business days.</p>
      <p>+ To get paid, choose a payment method and fill in your account details <a href="{{ route('member.user.profile') }}">here</a> (skip this if you have already done it).</p>
      <hr>
    </div>
  </div>
@endsection

@section('scripts')
  <script>
    $(function () {
      var $modal = $('#withdrawModal');
      var $backdrop = null;

      function openModal() {
        $backdrop = $('<div class="modal-backdrop fade in"></div>').appendTo('body');
        $('body').addClass('modal-open');
        $modal.show().addClass('in');
        $('#amount').trigger('focus');
      }

      function closeModal() {
        $modal.hide().removeClass('in');
        $('body').removeClass('modal-open');
        if ($backdrop) {
          $backdrop.remove();
        }
      }

      $('#withdraw-open').on('click', function (e) {
        e.preventDefault();
        openModal();
      });

      $('[data-dismiss-withdraw]').on('click', closeModal);

      @if ($errors->any() && old('amount') !== null)
        openModal();
      @endif
    });
  </script>
@endsection
