@extends('layouts.member')

@section('title', 'Top Links')

@section('content')
  <x-member.month-picker :months="$months" :selected="$month" />

  <div class="box box-success">
    <div class="box-header with-border">
      <i class="fa fa-fire"></i>
      <h3 class="box-title">Top 10 Links In Month</h3>
    </div>
    <div class="box-body">
      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th class="text-center">No</th>
              <th class="text-center">Links</th>
              <th class="text-center">Shorten Link</th>
              <th class="text-center">View</th>
              <th class="text-center">Link Earnings</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($links as $row)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td style="max-width: 500px; word-break: break-all;">{{ $row['link']->url }}</td>
                <td>{{ url($row['link']->alias) }}</td>
                <td class="text-right">{{ number_format($row['views']) }}</td>
                <td class="text-right">{{ number_format($row['earn_micro'] / \App\Support\Money::MICRO_PER_USD, 3) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted">No views in this month yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
