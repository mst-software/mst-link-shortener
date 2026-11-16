@props(['months', 'selected', 'range' => null])

<div class="text-center">
  <form method="get" class="form form-inline" style="margin: 10px auto;">
    @if ($range !== null)
      <input type="hidden" name="range" value="{{ $range }}">
    @endif
    <div class="form-group">
      <select name="month" class="form-control" onchange="this.form.submit()">
        @foreach ($months as $option)
          <option value="{{ $option }}" @selected($option === $selected)>Month {{ $option }}</option>
        @endforeach
      </select>
    </div>
  </form>
</div>
