{{-- A write-only secret field: the stored value is never rendered, only whether one is saved. --}}
@props(['name', 'label', 'help' => null, 'saved' => false, 'serverDefault' => false])

<div @class(['form-group', 'has-error' => $errors->has($name)])>
  <label for="{{ $name }}">
    {{ $label }}
    @if ($saved)
      <span class="label label-success">Saved</span>
    @elseif ($serverDefault)
      <span class="label label-info">Using the server default</span>
    @else
      <span class="label label-default">Not set</span>
    @endif
  </label>
  <input type="password" id="{{ $name }}" name="{{ $name }}" class="form-control" maxlength="255" autocomplete="new-password" value="" placeholder="{{ $saved ? 'Saved. Leave blank to keep it.' : 'Not set' }}">
  <span class="help-block">@error($name){{ $message }}@else{{ $help }} For safety the saved value is never shown again; type a new one to replace it.@enderror</span>
  @if ($saved)
    <div class="checkbox">
      <label><input type="checkbox" name="clear_{{ $name }}" value="1"> Remove the saved value</label>
    </div>
  @endif
</div>
