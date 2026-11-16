@extends('layouts.admin')

@section('title', $user->username)

@section('content')
  @if ($errors->any())
    <div class="alert alert-danger">
      @foreach ($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <p><a href="{{ route('admin.users.index') }}">&larr; All users</a></p>

  <div class="row">
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Profile</h3></div>
        <div class="box-body">
          <dl class="dl-horizontal">
            <dt>ID</dt><dd>{{ $user->id }}</dd>
            <dt>Username</dt><dd>{{ $user->username }}</dd>
            <dt>Email</dt>
            <dd>
              {{ $user->email }}
              @if ($user->hasVerifiedEmail())
                <span class="label label-success">Verified</span>
              @else
                <span class="label label-warning">Not verified</span>
              @endif
            </dd>
            <dt>Role</dt><dd>{{ $user->role->label() }}</dd>
            <dt>Status</dt>
            <dd><span @class(['label', 'label-success' => $user->isActive(), 'label-danger' => ! $user->isActive()])>{{ $user->status->label() }}</span></dd>
            <dt>Name</dt><dd>{{ $user->fullname ?: '-' }}</dd>
            <dt>Phone</dt><dd>{{ $user->phone_number ?: '-' }}</dd>
            <dt>Country</dt><dd>{{ $user->country ?: '-' }}</dd>
            <dt>Payout method</dt><dd>{{ $user->payout_method?->label() ?? '-' }}</dd>
            <dt>Traffic source</dt><dd>{{ $user->traffic_source ?: '-' }}</dd>
            <dt>Joined</dt><dd>{{ $user->created_at?->format('d/m/Y H:i') }}</dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Balances and activity</h3></div>
        <div class="box-body">
          <dl class="dl-horizontal">
            <dt>Available balance</dt><dd><strong>{{ App\Support\Money::format($user->balance_micro, 6) }}</strong></dd>
            <dt>Processing</dt><dd>{{ App\Support\Money::format($user->processing_micro, 6) }}</dd>
            <dt>Links</dt><dd>{{ number_format($linkSummary['total']) }} ({{ number_format($linkSummary['banned']) }} banned)</dd>
            <dt>Link views</dt><dd>{{ number_format($linkSummary['views']) }}</dd>
            <dt>Referred by</dt>
            <dd>
              @if ($user->referrer)
                <a href="{{ route('admin.users.show', $user->referrer) }}">{{ $user->referrer->username }}</a>
              @else
                -
              @endif
            </dd>
            <dt>Referrals</dt><dd>{{ number_format($referralsCount) }}</dd>
            <dt>Referral earnings</dt><dd>{{ App\Support\Money::format($referralEarningsMicro, 6) }}</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="box box-warning">
        <div class="box-header with-border"><h3 class="box-title">Account actions</h3></div>
        <div class="box-body">
          @if ($user->isActive())
            <form action="{{ route('admin.users.ban', $user) }}" method="post" onsubmit="return confirm('Ban this user? They are signed out and can no longer sign in.')" style="margin-bottom: 15px">
              @csrf
              <div class="form-group">
                <label for="ban-reason">Ban reason</label>
                <input type="text" id="ban-reason" name="reason" class="form-control" maxlength="200" required>
              </div>
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="ban_links" value="1">
                  Also ban all their links (cancels pending views and takes back released earnings; this is not undone by unbanning)
                </label>
              </div>
              <button type="submit" class="btn btn-danger btn-sm">Ban user</button>
            </form>
          @else
            <form action="{{ route('admin.users.unban', $user) }}" method="post" onsubmit="return confirm('Unban this user? They can sign in again.')" style="margin-bottom: 15px">
              @csrf
              <div class="form-group">
                <label for="unban-reason">Note (optional)</label>
                <input type="text" id="unban-reason" name="reason" class="form-control" maxlength="200">
              </div>
              <button type="submit" class="btn btn-success btn-sm">Unban user</button>
            </form>
          @endif

          @unless ($user->isAdvertiser())
            <form action="{{ route('admin.users.role', $user) }}" method="post" class="form-inline" onsubmit="return confirm('Change this user\'s role?')" style="margin-bottom: 15px">
              @csrf
              @method('PATCH')
              <label for="role">Role</label>
              <select id="role" name="role" class="form-control input-sm">
                @foreach ([App\Enums\UserRole::Member, App\Enums\UserRole::Admin] as $case)
                  <option value="{{ $case->value }}" @selected($user->role === $case)>{{ $case->label() }}</option>
                @endforeach
              </select>
              <input type="text" name="reason" class="form-control input-sm" maxlength="200" placeholder="Note (optional)">
              <button type="submit" class="btn btn-warning btn-sm">Change role</button>
            </form>
          @endunless

          <div>
            @unless ($user->hasVerifiedEmail())
              <form action="{{ route('admin.users.verify-email', $user) }}" method="post" style="display: inline" onsubmit="return confirm('Mark this email address as verified without the verification mail?')">
                @csrf
                <button type="submit" class="btn btn-default btn-sm">Mark email verified</button>
              </form>
              <form action="{{ route('admin.users.verification-email', $user) }}" method="post" style="display: inline">
                @csrf
                <button type="submit" class="btn btn-default btn-sm">Resend verification email</button>
              </form>
            @endunless
            <form action="{{ route('admin.users.password-reset', $user) }}" method="post" style="display: inline" onsubmit="return confirm('Email a password reset link to this user?')">
              @csrf
              <button type="submit" class="btn btn-default btn-sm">Send password reset link</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="box box-warning">
        <div class="box-header with-border"><h3 class="box-title">Adjust balance</h3></div>
        <div class="box-body">
          <form action="{{ route('admin.users.balance-adjustments.store', $user) }}" method="post" onsubmit="return confirm('Apply this balance adjustment? It is recorded in the ledger.')">
            @csrf
            <div class="form-group">
              <label class="radio-inline"><input type="radio" name="direction" value="credit" @checked(old('direction', 'credit') === 'credit')> Credit (add)</label>
              <label class="radio-inline"><input type="radio" name="direction" value="debit" @checked(old('direction') === 'debit')> Debit (remove)</label>
            </div>
            <div class="form-group">
              <label for="amount">Amount (USD)</label>
              <input type="number" id="amount" name="amount" class="form-control" step="0.000001" min="0.000001" max="100000" value="{{ old('amount') }}" required>
            </div>
            <div class="form-group">
              <label for="adjust-reason">Reason</label>
              <input type="text" id="adjust-reason" name="reason" class="form-control" maxlength="200" value="{{ old('reason') }}" required>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply adjustment</button>
            <p class="help-block">A debit can never take the available balance below zero.</p>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Recent transactions</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance</th><th>Description</th></tr></thead>
            <tbody>
              @forelse ($transactions as $transaction)
                <tr>
                  <td>{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $transaction->action->label() }}</td>
                  <td>{{ $transaction->type === 'in' ? '+' : '-' }}{{ App\Support\Money::format($transaction->amount_micro, 6) }}</td>
                  <td>{{ App\Support\Money::format($transaction->balance_after_micro, 6) }}</td>
                  <td>{{ $transaction->description }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted">No transactions.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Recent withdrawals</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($withdrawals as $withdrawal)
                <tr>
                  <td>{{ $withdrawal->created_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $withdrawal->method->label() }}</td>
                  <td>{{ App\Support\Money::format($withdrawal->amount_micro, 2) }}</td>
                  <td><span class="label {{ $withdrawal->status->badgeClass() }}">{{ $withdrawal->status->label() }}</span></td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted">No withdrawals.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Recent fraud flags</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead><tr><th>Date</th><th>Link</th><th>Reason</th><th>Resolved</th></tr></thead>
            <tbody>
              @forelse ($fraudFlags as $flag)
                <tr>
                  <td>{{ $flag->created_at->format('d/m/Y H:i') }}</td>
                  <td>@if ($flag->link)<code>{{ $flag->link->alias }}</code>@endif</td>
                  <td>{{ $flag->reason }}</td>
                  <td>{{ $flag->resolved_at ? 'Yes' : 'No' }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted">No fraud flags.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Recent referrals</h3></div>
        <div class="box-body table-responsive no-padding">
          <table class="table table-hover">
            <thead><tr><th>Username</th><th>Joined</th></tr></thead>
            <tbody>
              @forelse ($recentReferrals as $referral)
                <tr>
                  <td><a href="{{ route('admin.users.show', $referral) }}">{{ $referral->username }}</a></td>
                  <td>{{ $referral->created_at?->format('d/m/Y') }}</td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-center text-muted">No referrals.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="box">
    <div class="box-header with-border">
      <h3 class="box-title">Admin audit log</h3>
      <a href="{{ route('admin.audit-logs.index', ['q' => $user->username]) }}" class="pull-right">Search the full audit log</a>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Reason</th><th>Before</th><th>After</th></tr></thead>
        <tbody>
          @forelse ($auditLogs as $log)
            <tr>
              <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
              <td>{{ $log->admin?->username ?? '-' }}</td>
              <td>{{ $log->action->label() }}</td>
              <td>{{ $log->reason }}</td>
              <td><x-admin.audit-values :values="$log->old_values" /></td>
              <td><x-admin.audit-values :values="$log->new_values" /></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted">No admin actions recorded.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
