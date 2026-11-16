{{-- A labelled settings field with plain-English help text that is replaced by the validation error. --}}
@props(['name', 'label', 'help' => null, 'type' => 'text', 'value' => null])

<div @class(['form-group', 'has-error' => $errors->has($name)])>
  <label for="{{ $name }}">{{ $label }}</label>
  <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'form-control']) }} value="{{ old($name, $value) }}">
  <span class="help-block">@error($name){{ $message }}@else{{ $help }}@enderror</span>
</div>
