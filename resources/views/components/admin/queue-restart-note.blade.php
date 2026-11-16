{{-- Background workers read these settings when they start, so they need a restart to pick up changes. --}}
<p class="text-muted queue-restart-note">
  <i class="fa fa-info-circle"></i>
  Changes apply to the website right away. Background tasks (such as queued emails and scheduled jobs) keep the old values until they restart, so after saving ask whoever runs the server to run <code>php artisan queue:restart</code>.
</p>
