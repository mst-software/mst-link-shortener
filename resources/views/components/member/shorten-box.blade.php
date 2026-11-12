@props(['defaultLevel' => 1])

@php($selectedLevel = (int) old('ad_type', $defaultLevel))

<div class="box-short">
  <div class="box box-success box-solid shorten-member">
    <div class="box-body">
      <form method="post" accept-charset="utf-8" id="shorten" action="{{ url('links/shorten') }}">
        @csrf
        <div class="form-group">
          <input type="text" name="url" placeholder="Paste your long URL here..." required class="form-control" id="url" value="{{ old('url') }}">
        </div>
        <div class="row">
          <div class="col-sm-6">
            <div class="form-group">
              <label for="alias">Alias</label>
              <input type="text" name="alias" placeholder="Alias (optional)" class="form-control input-sm" id="alias" value="{{ old('alias') }}">
            </div>
          </div>
          <div class="col-sm-6">
            <div class="form-group">
              <label for="ad_type">Ad level</label>
              <select name="ad_type" id="ad_type" class="form-control input-sm">
                @foreach ([1, 2, 3] as $level)
                  <option value="{{ $level }}" @selected($selectedLevel === $level)>Level {{ $level }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
        <button class="btn btn-submit btn-primary" type="submit">Shorten</button>
      </form>
      <div class="shorten add-link-result"></div>
    </div>
  </div>
</div>
