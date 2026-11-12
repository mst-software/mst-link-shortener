@extends('layouts.member')

@section('title', 'Referrals')

@section('content')
  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title"><i class="fa fa-exchange"></i> My Referrals</h3>
    </div>
    <div class="box-body">
      <p>The LinkCash referral program is a great way to spread the word about this service and earn even more with your short links. Refer friends and receive {{ $commissionPercent }}% of their earnings for life.</p>

      <h3>Your referral link</h3>
      <pre>{{ $referralLink }}</pre>

      @if ($rows->isEmpty())
        <div class="text-center">You have not referred anyone yet. Share your link with your friends now.</div>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>Username</th>
                <th>Joined</th>
                <th>Commission earned</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($rows as $row)
                <tr>
                  <td>{{ $row['username'] }}</td>
                  <td>{{ $row['joined_at']->format('d/m/Y') }}</td>
                  <td>{{ \App\Support\Money::format($row['commission_micro']) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
