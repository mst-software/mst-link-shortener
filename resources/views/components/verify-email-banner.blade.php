@props(['user'])

@unless ($user->hasVerifiedEmail())
  <div class="alert alert-warning" id="verify-email-banner">
    <form method="post" action="{{ route('verification.send') }}" class="pull-right">
      @csrf
      <button type="submit" class="btn btn-default btn-sm">Resend verification email</button>
    </form>
    <i class="icon fa fa-envelope"></i>
    Please verify your email address <strong>{{ $user->email }}</strong>. {{ $slot }}
  </div>
@endunless
