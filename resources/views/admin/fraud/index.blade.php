@extends('layouts.admin')

@section('title', 'Fraud flags')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">{{ $showResolved ? 'All flags' : 'Open flags' }}</h3>
      <div class="box-tools">
        @if ($showResolved)
          <a href="{{ route('admin.fraud-flags.index') }}" class="btn btn-default btn-sm">Show open only</a>
        @else
          <a href="{{ route('admin.fraud-flags.index', ['resolved' => 1]) }}" class="btn btn-default btn-sm">Show handled too</a>
        @endif
      </div>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Date</th>
            <th>Reason</th>
            <th>Member</th>
            <th>Link</th>
            <th>Details</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($flags as $flag)
            <tr>
              <td>{{ $flag->created_at?->format('d/m/Y H:i') }}</td>
              <td>{{ str($flag->reason)->replace('_', ' ')->ucfirst() }}</td>
              <td>
                @if ($flag->user)
                  <a href="{{ route('admin.users.show', $flag->user) }}">{{ $flag->user->username }}</a>
                @else
                  -
                @endif
              </td>
              <td>
                @if ($flag->link)
                  <code>{{ $flag->link->alias }}</code>
                  @if ($flag->link->status === App\Enums\LinkStatus::Banned)
                    <span class="label label-danger">Banned</span>
                  @endif
                @else
                  -
                @endif
              </td>
              <td>
                @if ($flag->reason === App\Services\ReferralCommissionService::FLAG_REASON)
                  @php
                    $referredUser = $referredUsers->get((int) ($flag->details['referred_user_id'] ?? 0));
                    $heldMicro = (int) ($flag->details['held_commission_micro'] ?? 0);
                  @endphp
                  <div>
                    <strong>Referred member:</strong>
                    @if ($referredUser)
                      <a href="{{ route('admin.users.show', $referredUser) }}">{{ $referredUser->username }}</a>
                    @else
                      #{{ $flag->details['referred_user_id'] ?? '?' }}
                    @endif
                    signed up from the same IP address and browser as the referrer.
                  </div>
                  <div>
                    <strong>Signals:</strong>
                    @foreach ((array) ($flag->details['signals'] ?? []) as $signal)
                      <span class="label label-default">{{ App\Enums\FootprintSignal::tryFrom((string) $signal)?->label() ?? $signal }}</span>
                    @endforeach
                    (score {{ (int) ($flag->details['score'] ?? 0) }})
                  </div>
                  @if (! empty($flag->details['shared_ips']))
                    <div><strong>Shared IPs:</strong> {{ implode(', ', (array) $flag->details['shared_ips']) }}</div>
                  @endif
                  @if ($heldMicro > 0)
                    <div><strong>Held commission:</strong> {{ App\Support\Money::format($heldMicro, 6) }}</div>
                  @endif
                  @if (! empty($flag->details['paid_commission_micro']))
                    <div><strong>Paid after review:</strong> {{ App\Support\Money::format((int) $flag->details['paid_commission_micro'], 6) }}</div>
                  @endif
                @else
                  @foreach ((array) $flag->details as $key => $value)
                    @if ($value !== null && $value !== '')
                      <div><strong>{{ str($key)->replace('_', ' ')->ucfirst() }}:</strong> {{ is_scalar($value) ? $value : json_encode($value) }}</div>
                    @endif
                  @endforeach
                @endif
              </td>
              <td>
                @if ($flag->resolved_at)
                  <span class="label label-default">Handled</span>
                @else
                  <span class="label label-warning">Open</span>
                @endif
              </td>
              <td class="text-right">
                @if (! $flag->resolved_at && (int) ($flag->details['held_commission_micro'] ?? 0) > 0)
                  <form action="{{ route('admin.fraud-flags.pay-held-commission', $flag) }}" method="post" style="display: inline" onsubmit="return confirm('Pay the held commission to the referrer and mark this flag handled?')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-xs">Pay held commission</button>
                  </form>
                @endif
                @unless ($flag->resolved_at)
                  <form action="{{ route('admin.fraud-flags.resolve', $flag) }}" method="post" style="display: inline" @if ((int) ($flag->details['held_commission_micro'] ?? 0) > 0) onsubmit="return confirm('Mark handled without paying? The held commission will not be paid.')" @endif>
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-default btn-xs">Mark handled</button>
                  </form>
                @endunless
                @if ($flag->link && $flag->link->status !== App\Enums\LinkStatus::Banned)
                  <form action="{{ route('admin.fraud-flags.ban-link', $flag) }}" method="post" style="display: inline" onsubmit="return confirm('Ban this link? Pending views are cancelled and released earnings are taken back.')">
                    @csrf
                    <input type="hidden" name="reason" value="{{ str($flag->reason)->replace('_', ' ')->ucfirst() }}">
                    <button type="submit" class="btn btn-danger btn-xs">Ban link</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No fraud flags.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($flags->hasPages())
      <div class="box-footer">{{ $flags->links('pagination::bootstrap-4') }}</div>
    @endif
  </div>
@endsection
