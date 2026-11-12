@props(['name', 'placeholder', 'type' => 'text', 'maxlength' => null, 'id' => null, 'toggle' => false])

<div class="form-group {{ $type }} required">
  @if ($toggle)
    <div class="password-wrap">
      <input type="{{ $type }}" name="{{ $name }}" placeholder="{{ $placeholder }}" class="form-control" required="required" @if ($maxlength) maxlength="{{ $maxlength }}" @endif id="{{ $id ?? $name }}" value="">
      <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
        <svg class="pw-eye-on" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg class="pw-eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
      </button>
    </div>
  @else
    <input type="{{ $type }}" name="{{ $name }}" placeholder="{{ $placeholder }}" class="form-control" required="required" @if ($maxlength) maxlength="{{ $maxlength }}" @endif id="{{ $id ?? $name }}" value="">
  @endif
  {{ $slot }}
  <span class="help-block"></span>
</div>
