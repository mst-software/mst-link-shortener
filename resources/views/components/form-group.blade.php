@props(['name', 'label', 'textarea' => false, 'type' => 'text'])

<div class="form-group">
  <label for="{{ $name }}">{{ $label }}</label>
  @if ($textarea)
    <textarea name="{{ $name }}" class="form-control" id="{{ $name }}" rows="5"></textarea>
  @else
    <input type="{{ $type }}" name="{{ $name }}" class="form-control" id="{{ $name }}">
  @endif
  <p class="text-danger" aria-live="polite"></p>
</div>
