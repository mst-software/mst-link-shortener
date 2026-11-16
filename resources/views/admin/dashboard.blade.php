@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
  @if ($statsAreDelayed)
    <div class="alert alert-danger">
      <strong>Member statistics are not updating.</strong>
      <code>views:aggregate</code> has not succeeded in the last {{ App\Services\ScheduleHealth::JOBS['views:aggregate'] * 2 }} minutes, so member dashboards show stale numbers. Check that the scheduler (<code>php artisan schedule:run</code> every minute) is running.
    </div>
  @endif

  <div class="row">
    <div class="col-md-3 col-sm-6">
      <div class="info-box">
        <span class="info-box-icon bg-purple"><i class="fa fa-users"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">Users</span>
          <span class="info-box-number">{{ number_format($userCount) }}</span>
        </div>
      </div>
    </div>
  </div>

  <div class="box box-primary">
    <div class="box-header with-border">
      <h3 class="box-title">Scheduled jobs</h3>
    </div>
    <div class="box-body table-responsive no-padding">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Command</th>
            <th>Every</th>
            <th>Last success</th>
            <th>Last failure</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($scheduledJobs as $job)
            <tr>
              <td><code>{{ $job['command'] }}</code></td>
              <td>{{ $job['interval_minutes'] }} min</td>
              <td>
                @if ($job['last_success_at'])
                  {{ $job['last_success_at']->format('d/m/Y H:i') }}
                  <small class="text-muted">({{ $job['last_success_at']->diffForHumans() }})</small>
                @else
                  Never
                @endif
              </td>
              <td>
                @if ($job['last_failure_at'])
                  {{ $job['last_failure_at']->format('d/m/Y H:i') }}
                  <div class="text-muted small">{{ $job['last_failure_message'] }}</div>
                @else
                  -
                @endif
              </td>
              <td><span class="label {{ $job['status']->badgeClass() }}">{{ $job['status']->label() }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="box-footer text-muted small">A job is late when it has not succeeded within twice its interval. Daily jobs that have not run yet since setup are waiting.</div>
  </div>
@endsection
