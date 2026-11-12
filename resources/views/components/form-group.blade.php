@props(['name', 'label', 'textarea' => false])

<div class="form-group">
  <label for="{{ $name }}">{{ $label }}</label>
  @if ($textarea)
    <textarea name="{{ $name }}" class="form-control" id="{{ $name }}" rows="5"></textarea>
  @else
    <input type="text" name="{{ $name }}" class="form-control" id="{{ $name }}">
  @endif
  <p class="text-danger"></p>
</div>
