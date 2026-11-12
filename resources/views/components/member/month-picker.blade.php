@props(['months', 'selected'])

<div class="text-center">
  <form method="get" class="form form-inline" style="margin: 10px auto;">
    <div class="form-group">
      <select name="month" class="form-control" onchange="this.form.submit()">
        @foreach ($months as $option)
          <option value="{{ $option }}" @selected($option === $selected)>Month {{ $option }}</option>
        @endforeach
      </select>
    </div>
  </form>
</div>
