@props(['user'])

@unless ($user->hasVerifiedEmail())
  <div class="alert alert-warning" id="verify-email-banner" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px 16px">
    <div style="flex:1 1 260px">
      <i class="icon fa fa-envelope"></i>
      Please verify your email address <strong style="word-break:break-all">{{ $user->email }}</strong>. {{ $slot }}
    </div>
    <form method="post" action="{{ route('verification.send') }}" style="flex:0 0 auto">
      @csrf
      <button type="submit" class="btn btn-default btn-sm">Resend verification email</button>
    </form>
  </div>
@endunless
