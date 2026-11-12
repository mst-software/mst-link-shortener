@extends('layouts.member')

@section('title', 'Link Statistics')

@section('breadcrumb')
  <li>Links</li>
  <li class="active">Link Statistics</li>
@endsection

@section('content')
  <div class="box box-solid">
    <div class="box-body">
      <h4 style="word-break: break-all;">
        <a href="{{ $shortUrl }}" target="_blank" rel="nofollow noopener noreferrer">{{ $shortUrl }}</a>
      </h4>
      <table class="table table-bordered">
        <tbody>
          <tr>
            <th style="width: 200px;">Destination URL</th>
            <td style="word-break: break-all;">{{ $link->url }}</td>
          </tr>
          <tr>
            <th>Short link</th>
            <td>{{ $shortUrl }}</td>
          </tr>
          <tr>
            <th>Created</th>
            <td>{{ $link->created_at?->format('H:i d/m/Y') }}</td>
          </tr>
          <tr>
            <th>Level</th>
            <td>Level {{ $link->level }}</td>
          </tr>
          <tr>
            <th>Valid views</th>
            <td>{{ number_format($link->views_count) }}</td>
          </tr>
          <tr>
            <th>Earnings</th>
            <td>{{ \App\Services\DashboardStats::display($totalEarnMicro) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="box box-primary">
    <div class="box-header with-border">
      <i class="fa fa-chart-bar"></i>
      <h3 class="box-title">Last 30 days</h3>
    </div>
    <div class="box-body">
      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th class="text-center">Day</th>
              <th class="text-center">Views</th>
              <th class="text-center">Earnings ($)</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($days as $day)
              <tr>
                <td class="text-center">{{ \Carbon\CarbonImmutable::parse($day['date'])->format('d/m/Y') }}</td>
                <td class="text-right">{{ number_format($day['views']) }}</td>
                <td class="text-right">{{ number_format($day['earn_micro'] / \App\Support\Money::MICRO_PER_USD, 3) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="text-center text-muted">No views in the last 30 days.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
