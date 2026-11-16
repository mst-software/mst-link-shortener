@extends('layouts.visit')

@section('title', 'Untitled #'.$alias)

@section('content')
<div class="lk-container">
  <div class="lk-box-main">
    <div class="lk-box-body">
      <div id="visit-password" class="lk-card">
        <h1 class="lk-title">This link is protected by a password</h1>

        <form method="post" action="{{ route('visit.unlock', ['alias' => $alias]) }}">
          @csrf
          <div class="lk-code">
            <input
              id="link-password"
              name="password"
              type="password"
              class="lk-input @error('password') is-error @enderror"
              maxlength="64"
              autocomplete="off"
              required
              autofocus
              placeholder="Enter the password"
              aria-label="Link password"
              @error('password') aria-invalid="true" aria-describedby="link-password-error" @enderror
            >
          </div>
          <button type="submit" class="lk-btn lk-btn-success">Continue</button>
          @error('password')
            <p id="link-password-error" class="lk-status is-error" role="alert">{{ $message }}</p>
          @enderror
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
